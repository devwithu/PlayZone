<?php

namespace App\Enums;

enum UserStatus: string
{
    /** 소셜 로그인으로 계정만 만들어지고 가입 완료(약관·만 14세·닉네임) 전 */
    case Pending = 'pending';

    case Active = 'active';

    case Suspended = 'suspended';

    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '가입 미완료',
            self::Active => '정상',
            self::Suspended => '정지',
            self::Withdrawn => '탈퇴',
        };
    }

    /** 관리자 화면 배지 색 (Filament 색 이름) */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Withdrawn => 'gray',
        };
    }
}
