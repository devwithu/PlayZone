<?php

namespace App\Data;

use App\Models\User;

/**
 * 가입 완료 결과. 이미 가입을 마친 회원이 다시 보내면 `point.earned`는 0이다.
 */
final readonly class SignupResult
{
    public function __construct(
        public User $user,
        public PointResult $point,
    ) {}
}
