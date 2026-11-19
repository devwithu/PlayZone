<?php

namespace App\Enums;

enum UserStatus: string
{
    /** 소셜 로그인으로 계정만 만들어지고 가입 완료(약관·만 14세·닉네임) 전 */
    case Pending = 'pending';

    case Active = 'active';

    case Suspended = 'suspended';

    case Withdrawn = 'withdrawn';
}
