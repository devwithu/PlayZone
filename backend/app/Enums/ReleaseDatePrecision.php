<?php

namespace App\Enums;

/**
 * 출시일을 어디까지 알고 있는지 (ERD §4 game_release_dates.date_precision).
 * 월·분기·연 단위는 그 기간의 첫날을 release_date에 저장하고, 미정(tba)은 NULL이다.
 */
enum ReleaseDatePrecision: string
{
    case Day = 'day';

    case Month = 'month';

    case Quarter = 'quarter';

    case Year = 'year';

    case Tba = 'tba';

    public function label(): string
    {
        return match ($this) {
            self::Day => '일',
            self::Month => '월',
            self::Quarter => '분기',
            self::Year => '연',
            self::Tba => '미정',
        };
    }
}
