<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * 서비스 일자: 한국 시간(KST) 00:00 기준의 날짜 (포인트 정책 §1, §2-7).
 * DB 시각은 UTC로 저장하고, 하루 단위 규칙(출석, 일일 한도, 출시 일정)은 이 날짜로 판단한다.
 */
final class ServiceDate
{
    public const TIMEZONE = 'Asia/Seoul';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }
}
