<?php

namespace App\Services;

use App\Enums\PointType;
use App\Enums\RankingType;
use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Http\Resources\Api\V1\Author;
use App\Models\RankingSnapshot;
use App\Models\User;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * 랭킹 집계와 조회 (REQ-508, 랭킹 정책). 웹과 앱 API가 함께 쓴다.
 *
 * - 집계는 `rankings:snapshot` 명령이 매시 정각에 이번 기간을 다시 계산하고, 기간이 바뀐 첫날에는 지난 기간도 마지막으로 다시 계산한다.
 * - 한 기간의 결과는 트랜잭션 안에서 지우고 다시 넣으므로 조회 중에 반쯤 바뀐 순위를 보지 않는다.
 * - 정상(active) 회원만 순위에 든다. 같은 점수는 같은 순위(1, 1, 3 …)다.
 */
class RankingService
{
    public const TOP = 100;

    /**
     * 주간 포인트에 넣는 활동 적립 사유 (랭킹 정책 §2). 가입·이벤트 보상·관리자 지급은 빼고, 회수된 만큼은 깎는다.
     */
    public const ACTIVITY_TYPES = [
        PointType::Attendance, PointType::AttendanceStreak7, PointType::AttendanceStreak30,
        PointType::ReviewCreate, PointType::ReviewLikeReceived, PointType::PostCreate, PointType::CommentCreate,
    ];

    /**
     * 이번 기간을 집계하고, 오늘이 새 기간의 첫날이면 지난 기간도 다시 집계한다.
     *
     * @return array<string, int> "{type} {start}" => 순위에 든 회원 수
     */
    public function snapshotCurrent(): array
    {
        $today = ServiceDate::today();
        $result = [];

        foreach (RankingType::cases() as $type) {
            $start = $type->periodStart($today);
            $result["{$type->value} {$start->toDateString()}"] = $this->snapshot($type, $start);

            if ($start->equalTo($today)) {
                $previous = $type->previousStart($start);
                $result["{$type->value} {$previous->toDateString()}"] = $this->snapshot($type, $previous);
            }
        }

        return $result;
    }

    /**
     * 한 기간을 다시 집계한다. 순위에 든 회원 수를 돌려준다.
     */
    public function snapshot(RankingType $type, CarbonImmutable $anyDateInPeriod): int
    {
        $start = $type->periodStart($anyDateInPeriod);
        $end = $type->periodEnd($start);
        [$scoreSql, $bindings] = $this->scoreQuery($type, $start, $end);

        return DB::transaction(function () use ($type, $start, $scoreSql, $bindings) {
            RankingSnapshot::query()->where('ranking_type', $type->value)->whereDate('snapshot_date', $start)->delete();

            return DB::affectingStatement(
                "INSERT INTO ranking_snapshots (ranking_type, snapshot_date, user_id, score, rank, created_at)
                 SELECT ?, ?, s.user_id, s.score, RANK() OVER (ORDER BY s.score DESC), now()
                 FROM ({$scoreSql}) s
                 WHERE s.score > 0",
                [$type->value, $start->toDateString(), ...$bindings],
            );
        });
    }

    /**
     * 랭킹 화면·API 데이터. 아직 집계하지 않은 기간이면 items가 비고 updated_at이 null이다.
     *
     * @return array<string, mixed>
     */
    public function board(RankingType $type, ?CarbonImmutable $date, ?User $viewer): array
    {
        $today = ServiceDate::today();
        $current = $type->periodStart($today);
        $start = $type->periodStart($date ?? $today);
        if ($start->greaterThan($current)) {
            $start = $current;
        }

        $base = RankingSnapshot::query()->where('ranking_type', $type->value)->whereDate('snapshot_date', $start);
        $rows = (clone $base)->with('user.level')->where('rank', '<=', self::TOP)->orderBy('rank')->orderBy('user_id')->limit(self::TOP)->get();
        $mine = $viewer === null ? null : (clone $base)->where('user_id', $viewer->id)->first();
        $earliest = RankingSnapshot::query()->where('ranking_type', $type->value)->min('snapshot_date');
        $previous = $type->previousStart($start);

        return [
            'type' => $type->value,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $type->periodEnd($start)->toDateString(),
                'label' => $type->periodLabel($start),
                'is_current' => $start->equalTo($current),
            ],
            /** 마지막 집계 시각 (UTC) */
            'updated_at' => $rows->max('created_at')?->toIso8601ZuluString(),
            'items' => $rows->map(fn (RankingSnapshot $r) => [
                'rank' => $r->rank,
                'user' => Author::from($r->user),
                'score' => $r->score,
            ])->values()->all(),
            /** 로그인 회원의 순위. 순위에 없으면(점수 0) rank가 null. 비회원이면 me가 null */
            'me' => $viewer === null ? null : ['rank' => $mine?->rank, 'score' => $mine->score ?? 0],
            'prev_date' => $earliest !== null && $previous->toDateString() >= substr((string) $earliest, 0, 10) ? $previous->toDateString() : null,
            'next_date' => $start->lessThan($current) ? $type->nextStart($start)->toDateString() : null,
        ];
    }

    /**
     * 회원별 점수 쿼리 (user_id, score)
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function scoreQuery(RankingType $type, CarbonImmutable $start, CarbonImmutable $end): array
    {
        if ($type === RankingType::PointsWeekly) {
            $types = array_map(fn (PointType $t) => $t->value, self::ACTIVITY_TYPES);
            $placeholders = implode(', ', array_fill(0, count($types), '?'));

            // 기간 안에 받은 활동 포인트에서, 그 적립을 나중에 회수한 만큼을 뺀다
            return [
                "SELECT pt.user_id, SUM(pt.amount + COALESCE(rv.amount, 0))::integer AS score
                 FROM point_transactions pt
                 JOIN users u ON u.id = pt.user_id AND u.status = ?
                 LEFT JOIN point_transactions rv
                   ON rv.type = ? AND rv.reference_type = 'point_transaction' AND rv.reference_id = pt.id
                 WHERE pt.type IN ({$placeholders}) AND pt.service_date BETWEEN ? AND ?
                 GROUP BY pt.user_id",
                [UserStatus::Active->value, PointType::Revoke->value, ...$types, $start->toDateString(), $end->toDateString()],
            ];
        }

        // 기간(KST) 안에 쓴 리뷰 중 지금 공개 상태인 것
        $from = CarbonImmutable::parse($start->toDateString(), ServiceDate::TIMEZONE)->utc();
        $to = CarbonImmutable::parse($end->toDateString(), ServiceDate::TIMEZONE)->addDay()->utc();

        return [
            'SELECT r.user_id, COUNT(*)::integer AS score
             FROM game_reviews r
             JOIN users u ON u.id = r.user_id AND u.status = ?
             WHERE r.status = ? AND r.created_at >= ? AND r.created_at < ?
             GROUP BY r.user_id',
            [UserStatus::Active->value, ReviewStatus::Published->value, $from, $to],
        ];
    }
}
