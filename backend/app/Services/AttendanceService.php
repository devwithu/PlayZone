<?php

namespace App\Services;

use App\Data\AttendanceResult;
use App\Enums\ApiErrorCode;
use App\Enums\PointType;
use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\PointPolicy;
use App\Models\User;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * 출석 (REQ-501~503, 포인트 정책 §3·§5). 웹과 앱이 같이 쓴다.
 *
 * - 날짜는 서버의 KST 서비스 일자다. 클라이언트는 날짜를 보내지 않는다.
 * - 하루 한 번: 회원 행 잠금으로 같은 회원의 요청을 한 줄로 세우고, UNIQUE (user_id, attendance_date)가 최종적으로 막는다.
 * - 포인트는 출석 행과 같은 트랜잭션에서 PointService로 지급한다(키 `attendance:{user_id}:{date}`).
 * - 연속 일수: 어제 출석했으면 +1, 아니면 1. 30일 다음은 다시 1. 7일째·30일째에 보너스.
 */
class AttendanceService
{
    /** 연속 출석 보너스: 연속 일수 => 지급 사유 */
    public const STREAK_BONUSES = [
        7 => PointType::AttendanceStreak7,
        30 => PointType::AttendanceStreak30,
    ];

    public const MAX_STREAK = 30;

    public function __construct(private readonly PointService $points) {}

    /**
     * 오늘 출석한다. 이미 출석했으면 409 ATTENDANCE_ALREADY_DONE.
     */
    public function check(User $user): AttendanceResult
    {
        $today = ServiceDate::today();

        try {
            return DB::transaction(function () use ($user, $today) {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);

                if ($this->find($user, $today) !== null) {
                    throw ApiException::of(ApiErrorCode::AttendanceAlreadyDone);
                }

                $attendance = Attendance::query()->create([
                    'user_id' => $user->id,
                    'attendance_date' => $today->toDateString(),
                    'streak_days' => $this->nextStreak($user, $today),
                ]);

                $date = $today->toDateString();
                $point = $this->points->earn(
                    $user,
                    PointType::Attendance,
                    "attendance:{$user->id}:{$date}",
                    referenceType: 'attendance',
                    referenceId: $attendance->id,
                );

                $bonuses = [];
                if (($bonusType = self::STREAK_BONUSES[$attendance->streak_days] ?? null) !== null) {
                    $bonuses[] = $this->points->earn(
                        $user,
                        $bonusType,
                        "{$bonusType->value}:{$user->id}:{$date}",
                        referenceType: 'attendance',
                        referenceId: $attendance->id,
                    );
                }

                return new AttendanceResult($attendance, $point, $bonuses);
            });
        } catch (UniqueConstraintViolationException) {
            // 잠금을 거치지 않은 경로에서 같은 날 행이 먼저 들어간 경우. 트랜잭션은 이미 롤백됐다
            throw ApiException::of(ApiErrorCode::AttendanceAlreadyDone);
        }
    }

    /**
     * 오늘 출석 상태 (GET /attendance/today, 웹 출석 화면).
     *
     * @return array{date: string, attended: bool, streak_days: int, points: int|null, next_bonus: array{type: string, streak_days: int, days_left: int, points: int}|null}
     */
    public function today(User $user): array
    {
        $today = ServiceDate::today();
        $todayRecord = $this->find($user, $today);
        $yesterday = $this->find($user, $today->subDay());

        // 지금까지 이어진 연속 일수. 오늘 아직 안 했어도 어제까지 이어졌으면 그 값
        $streak = $todayRecord->streak_days ?? $yesterday->streak_days ?? 0;
        // 다음 출석이 몇 일째가 되는지 (30일 다음은 1일째)
        $nextDay = $streak >= self::MAX_STREAK ? 1 : $streak + 1;

        $policies = PointPolicy::query()
            ->whereIn('action', [PointType::Attendance->value, ...array_map(fn (PointType $t) => $t->value, self::STREAK_BONUSES)])
            ->where('is_active', true)
            ->pluck('points', 'action');

        $nextBonus = null;
        foreach (self::STREAK_BONUSES as $days => $type) {
            if ($days >= $nextDay && isset($policies[$type->value])) {
                $nextBonus = [
                    'type' => $type->value,
                    'streak_days' => $days,
                    // 앞으로 며칠 더 출석하면 받는지 (1이면 다음 출석에 받음)
                    'days_left' => $days - $nextDay + 1,
                    'points' => (int) $policies[$type->value],
                ];
                break;
            }
        }

        return [
            'date' => $today->toDateString(),
            'attended' => $todayRecord !== null,
            'streak_days' => $streak,
            'points' => isset($policies[PointType::Attendance->value]) ? (int) $policies[PointType::Attendance->value] : null,
            'next_bonus' => $nextBonus,
        ];
    }

    /**
     * 한 달 출석 기록 (GET /attendance/history, 웹 출석 달력).
     *
     * @param  CarbonImmutable  $month  그 달의 아무 날
     * @return array{month: string, count: int, days: list<array{date: string, streak_days: int}>}
     */
    public function month(User $user, CarbonImmutable $month): array
    {
        $records = Attendance::query()
            ->where('user_id', $user->id)
            ->whereBetween('attendance_date', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()])
            ->orderBy('attendance_date')
            ->get(['attendance_date', 'streak_days']);

        $days = [];
        foreach ($records as $record) {
            $days[] = ['date' => $record->attendance_date->toDateString(), 'streak_days' => $record->streak_days];
        }

        return ['month' => $month->format('Y-m'), 'count' => count($days), 'days' => $days];
    }

    private function nextStreak(User $user, CarbonImmutable $today): int
    {
        $previous = $this->find($user, $today->subDay())->streak_days ?? 0;

        return $previous >= self::MAX_STREAK ? 1 : $previous + 1;
    }

    private function find(User $user, CarbonImmutable $date): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $user->id)
            ->where('attendance_date', $date->toDateString())
            ->first();
    }
}
