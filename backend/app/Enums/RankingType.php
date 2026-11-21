<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * 랭킹 종류 (REQ-508, 랭킹 정책 §2). 기간은 KST 기준이다.
 */
enum RankingType: string
{
    /** 주간 포인트: 월요일 0시 ~ 일요일 24시에 활동으로 받은 포인트 */
    case PointsWeekly = 'points_weekly';

    /** 월간 리뷰: 그 달에 쓴 공개 리뷰 수 */
    case ReviewsMonthly = 'reviews_monthly';

    public function label(): string
    {
        return match ($this) {
            self::PointsWeekly => '주간 포인트',
            self::ReviewsMonthly => '월간 리뷰',
        };
    }

    public function scoreUnit(): string
    {
        return match ($this) {
            self::PointsWeekly => 'P',
            self::ReviewsMonthly => '개',
        };
    }

    /**
     * $date(KST 날짜)가 속한 기간의 시작일
     */
    public function periodStart(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::PointsWeekly => $date->startOfWeek(CarbonImmutable::MONDAY)->startOfDay(),
            self::ReviewsMonthly => $date->startOfMonth()->startOfDay(),
        };
    }

    /**
     * 기간의 마지막 날 (포함)
     */
    public function periodEnd(CarbonImmutable $start): CarbonImmutable
    {
        return match ($this) {
            self::PointsWeekly => $start->addDays(6),
            self::ReviewsMonthly => $start->endOfMonth()->startOfDay(),
        };
    }

    public function previousStart(CarbonImmutable $start): CarbonImmutable
    {
        return $this->periodStart($start->subDay());
    }

    public function nextStart(CarbonImmutable $start): CarbonImmutable
    {
        return $this->periodEnd($start)->addDay();
    }

    /**
     * 화면 표시용 기간 이름. 예: `2026.11.16 ~ 11.22`, `2026년 11월`
     */
    public function periodLabel(CarbonImmutable $start): string
    {
        if ($this === self::ReviewsMonthly) {
            return $start->format('Y년 n월');
        }

        $end = $this->periodEnd($start);

        return $start->format('Y.n.j').' ~ '.$end->format('n.j');
    }
}
