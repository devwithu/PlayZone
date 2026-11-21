<?php

namespace App\Enums;

/**
 * 등급이 바뀐 이유 (ERD §8 user_level_histories.reason).
 */
enum LevelChangeReason: string
{
    /** 포인트 적립으로 올라감 */
    case Earn = 'earn';

    /** 포인트 회수로 내려감 */
    case Revoke = 'revoke';

    /** 관리자가 등급 구간을 바꾸거나 등급을 지워서 다시 계산됨 */
    case Admin = 'admin';
}
