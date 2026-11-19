<?php

namespace App\Concerns;

use App\Rules\UniqueNickname;
use Illuminate\Contracts\Validation\ValidationRule;

trait ProfileValidationRules
{
    /**
     * 닉네임: 2~12자, 한글·영문·숫자·밑줄, 대소문자 구분 없이 중복 불가 (REQ-004).
     *
     * @return array<int, ValidationRule|string>
     */
    protected function nicknameRules(?int $ignoreUserId = null): array
    {
        return [
            'required',
            'string',
            'min:2',
            'max:12',
            'regex:/^[가-힣a-zA-Z0-9_]+$/u',
            new UniqueNickname($ignoreUserId),
        ];
    }
}
