<?php

namespace App\Services;

use App\Data\ReleaseScheduleEntry;
use App\Enums\GameStatus;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 출시 일정 (api-spec §7.4, 화면 설계 W-08). 앱 API와 웹 화면이 함께 쓴다.
 *
 * 공개 게임의 취소되지 않은 출시일만 보인다. 출시일은 KST 날짜이고, 월·분기·연 단위는 그 기간의 첫날로
 * 저장되어 있으므로 "기간"으로 다룬다. 예: 2027년 1분기 = 2027-01-01 ~ 2027-03-31.
 */
class ReleaseService
{
    public const TIMEZONE = ServiceDate::TIMEZONE;

    /** 출시일 기간의 마지막 날 (일 단위면 그날) */
    private const PERIOD_END = "((rd.release_date + CASE rd.date_precision WHEN 'month' THEN interval '1 month' WHEN 'quarter' THEN interval '3 months' WHEN 'year' THEN interval '1 year' ELSE interval '1 day' END)::date - 1)";

    /** 기간이 같은 날 끝나면 일 → 월 → 분기 → 연 단위 순 */
    private const PRECISION_ORDER = "CASE rd.date_precision WHEN 'day' THEN 1 WHEN 'month' THEN 2 WHEN 'quarter' THEN 3 WHEN 'year' THEN 4 ELSE 5 END";

    /**
     * 기간별 출시 일정 (REQ-201). 출시일 기간이 [from, to]와 겹치는 것을 기간이 끝나는 날 순으로. 미정은 제외.
     *
     * @param  list<string>  $platforms  플랫폼 slug (하나라도 해당하면 포함)
     * @return Collection<int, ReleaseScheduleEntry>
     */
    public function schedule(CarbonImmutable $from, CarbonImmutable $to, array $platforms = []): Collection
    {
        $rows = $this->groupedQuery($platforms)
            ->where('rd.release_date', '<=', $to->toDateString())
            ->whereRaw(self::PERIOD_END.' >= ?', [$from->toDateString()])
            ->get();

        return $this->hydrate($rows);
    }

    /**
     * 출시 예정 (REQ-202). 아직 출시되지 않았고 출시일 기간이 오늘 이후인 것을 기간이 끝나는 날 순으로. 미정은 맨 뒤.
     *
     * @param  list<string>  $platforms
     * @return LengthAwarePaginator<int, ReleaseScheduleEntry>
     */
    public function upcoming(array $platforms = [], int $perPage = 20): LengthAwarePaginator
    {
        $paginator = $this->groupedQuery($platforms)
            ->where('rd.release_status', '!=', ReleaseStatus::Released->value)
            ->where(fn (Builder $q) => $q
                ->whereNull('rd.release_date')
                ->orWhereRaw(self::PERIOD_END.' >= ?', [self::today()->toDateString()]))
            ->paginate($perPage);

        $entries = $this->hydrate(collect($paginator->items()));

        /** @var LengthAwarePaginator<int, ReleaseScheduleEntry> */
        return $paginator->setCollection($entries);
    }

    public static function today(): CarbonImmutable
    {
        return ServiceDate::today();
    }

    /**
     * 게임·날짜·정밀도·상태가 같은 출시일 행을 한 줄로 묶는다. 플랫폼은 관리자가 정한 순서.
     *
     * @param  list<string>  $platforms
     */
    private function groupedQuery(array $platforms): Builder
    {
        return DB::table('game_release_dates as rd')
            ->join('games as g', 'g.id', '=', 'rd.game_id')
            ->join('platforms as p', 'p.id', '=', 'rd.platform_id')
            ->where('g.status', GameStatus::Published->value)
            ->where('rd.release_status', '!=', ReleaseStatus::Cancelled->value)
            ->when($platforms !== [], fn (Builder $q) => $q->whereIn('p.slug', $platforms))
            ->groupBy('rd.game_id', 'rd.release_date', 'rd.date_precision', 'rd.release_status', 'g.title')
            ->select('rd.game_id', 'rd.release_date', 'rd.date_precision', 'rd.release_status')
            ->selectRaw("string_agg(rd.platform_id::text, ',' ORDER BY p.sort_order, p.id) AS platform_ids")
            // 기간이 끝나는 날 순: "12월 중"은 12월의 날짜별 출시 뒤, 미정은 맨 뒤
            ->orderByRaw(self::PERIOD_END.' ASC NULLS LAST')
            ->orderByRaw(self::PRECISION_ORDER)
            ->orderBy('g.title')
            ->orderBy('rd.game_id');
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, ReleaseScheduleEntry>
     */
    private function hydrate(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $games = Game::query()->with('cover')->findMany($rows->pluck('game_id')->unique()->all())->keyBy('id');
        $platforms = Platform::query()->get()->keyBy('id');

        return $rows->map(function (object $row) use ($games, $platforms) {
            /** @var object{game_id: int, release_date: string|null, date_precision: string, release_status: string, platform_ids: string} $row */
            $release = new GameReleaseDate([
                'release_date' => $row->release_date,
                'date_precision' => $row->date_precision,
                'release_status' => $row->release_status,
            ]);

            return new ReleaseScheduleEntry(
                game: $games[$row->game_id],
                release: $release,
                platforms: collect(explode(',', $row->platform_ids))->map(fn (string $id) => $platforms[(int) $id])->values(),
            );
        })->values();
    }
}
