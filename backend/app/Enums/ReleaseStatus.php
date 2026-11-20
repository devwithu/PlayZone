<?php

namespace App\Enums;

/** 플랫폼별 출시 상태 (ERD §4 game_release_dates.release_status) */
enum ReleaseStatus: string
{
    /** 발표만 됨 */
    case Announced = 'announced';

    /** 출시일 확정 */
    case Scheduled = 'scheduled';

    case Delayed = 'delayed';

    /** 출시됨. 이 행이 하나라도 있으면 리뷰를 쓸 수 있다 (리뷰 정책 §2) */
    case Released = 'released';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Announced => '발표',
            self::Scheduled => '출시 예정',
            self::Delayed => '연기',
            self::Released => '출시',
            self::Cancelled => '취소',
        };
    }
}
