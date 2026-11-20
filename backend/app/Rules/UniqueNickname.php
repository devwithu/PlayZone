<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 닉네임 중복 검사. DB의 lower(nickname) UNIQUE 인덱스와 같은 기준(대소문자 무시)으로 확인한다.
 * 탈퇴 회원용 접두어 withdrawn_도 막는다.
 */
class UniqueNickname implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // 탈퇴 회원의 익명 닉네임(withdrawn_{id})과 겹치지 않게 예약한다 (ERD §12)
        if (str_starts_with(mb_strtolower($value), 'withdrawn_')) {
            $fail('사용할 수 없는 닉네임입니다.');

            return;
        }

        $exists = User::query()
            ->whereRaw('lower(nickname) = ?', [mb_strtolower($value)])
            ->when($this->ignoreUserId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($exists) {
            $fail('이미 사용 중인 닉네임입니다.');
        }
    }
}
