<?php

namespace App\Data;

use App\Enums\PointType;
use App\Models\Attendance;

/**
 * 출석 처리 결과 (api-spec §7.6 POST /attendance).
 */
final readonly class AttendanceResult
{
    /**
     * @param  list<PointResult>  $bonuses  연속 출석 보너스 (7일째·30일째)
     */
    public function __construct(
        public Attendance $attendance,
        /** 기본 출석 포인트 */
        public PointResult $point,
        public array $bonuses = [],
    ) {}

    /** 기본 + 보너스 합계 */
    public function totalEarned(): int
    {
        return $this->point->earned + array_sum(array_map(fn (PointResult $b) => $b->earned, $this->bonuses));
    }

    public function message(): string
    {
        $bonus = array_find($this->bonuses, fn (PointResult $b) => $b->earned > 0);
        if ($bonus !== null) {
            $days = $bonus->type === PointType::AttendanceStreak30 ? 30 : 7;

            return "출석했습니다. {$days}일 연속 출석 보너스를 받았습니다!";
        }

        return $this->totalEarned() > 0 ? "출석했습니다. {$this->totalEarned()}P를 받았습니다." : '출석했습니다.';
    }

    /**
     * @return array{attendance_date: string, streak_days: int, point: array{earned: int, balance: int, type: string, limited: bool}, bonuses: list<array{type: string, earned: int}>}
     */
    public function toArray(): array
    {
        $last = $this->bonuses === [] ? $this->point : $this->bonuses[array_key_last($this->bonuses)];

        return [
            'attendance_date' => $this->attendance->attendance_date->toDateString(),
            'streak_days' => $this->attendance->streak_days,
            // 기본과 보너스를 합친 지급액. 잔액은 모두 지급한 뒤의 값
            'point' => [...$this->point->toArray(), 'earned' => $this->totalEarned(), 'balance' => $last->balance],
            'bonuses' => array_values(array_map(
                fn (PointResult $b) => ['type' => $b->type->value, 'earned' => $b->earned],
                array_filter($this->bonuses, fn (PointResult $b) => $b->earned > 0),
            )),
        ];
    }
}
