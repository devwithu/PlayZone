<?php

namespace App\Enums;

enum GameImageType: string
{
    /** 게임당 하나 (부분 UNIQUE 인덱스) */
    case Cover = 'cover';

    case Screenshot = 'screenshot';

    case Banner = 'banner';

    public function label(): string
    {
        return match ($this) {
            self::Cover => '표지',
            self::Screenshot => '스크린샷',
            self::Banner => '배너',
        };
    }
}
