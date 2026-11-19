<?php

namespace App\Enums;

/**
 * 로그인 수단. 지금은 Google·Apple만 사용한다.
 * 이메일·비밀번호 가입을 추가하면 Password 케이스를 추가한다 (ADR-003 §8).
 */
enum LoginProvider: string
{
    case Google = 'google';

    case Apple = 'apple';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Apple => 'Apple',
        };
    }
}
