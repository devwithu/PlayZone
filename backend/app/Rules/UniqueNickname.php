<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 닉네임 중복 검사. DB의 lower(nickname) UNIQUE 인덱스와 같은 기준(대소문자 무시)으로 확인한다.
 */
class UniqueNickname implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
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
