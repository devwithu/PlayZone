<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SignupService
{
    /**
     * 가입 완료 처리 (ADR-003 §4).
     *
     * 이메일·비밀번호 가입을 추가하면, 그 회원은 이메일 인증도 완료 조건에 들어간다 (ADR-003 §8).
     * 가입 포인트(포인트 정책 §3)는 3단계에서 PointService를 만들 때 이 메서드에서 지급한다.
     */
    public function complete(User $user, string $nickname, bool $marketingAgreed): User
    {
        return DB::transaction(function () use ($user, $nickname, $marketingAgreed) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $user->isPending()) {
                return $user;
            }

            $now = now();
            $user->nickname = $nickname;
            $user->status = UserStatus::Active;
            $user->age_confirmed_at = $now;
            $user->terms_agreed_at = $now;
            $user->marketing_agreed_at = $marketingAgreed ? $now : null;
            $user->signup_completed_at = $now;
            $user->save();

            $memberRoleId = Role::query()->where('slug', 'member')->value('id');
            if ($memberRoleId !== null) {
                $user->roles()->syncWithoutDetaching([$memberRoleId]);
            }

            return $user;
        });
    }
}
