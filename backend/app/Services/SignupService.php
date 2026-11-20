<?php

namespace App\Services;

use App\Concerns\ProfileValidationRules;
use App\Data\PointResult;
use App\Data\SignupResult;
use App\Enums\PointType;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SignupService
{
    use ProfileValidationRules;

    public function __construct(private readonly PointService $points) {}

    /**
     * 닉네임 사용 가능 여부 (입력 중 확인용, 웹·API 공용).
     * 형식 오류도 "사용 불가"로 알려 주며, 요청 자체를 실패시키지 않는다.
     *
     * @return array{available: bool, message: string}
     */
    public function checkNickname(?string $nickname, User $user): array
    {
        $validator = Validator::make(
            ['nickname' => $nickname],
            ['nickname' => $this->nicknameRules($user->id)],
            attributes: ['nickname' => '닉네임'],
        );

        return [
            'available' => $validator->passes(),
            'message' => $validator->errors()->first('nickname') ?: '사용할 수 있는 닉네임입니다.',
        ];
    }

    /**
     * 가입 완료 처리 (ADR-003 §4).
     *
     * 이메일·비밀번호 가입을 추가하면, 그 회원은 이메일 인증도 완료 조건에 들어간다 (ADR-003 §8).
     * 가입 포인트(포인트 정책 §3, 키 `signup:{user_id}`)를 같은 트랜잭션에서 지급한다. 재시도하면 earned 0.
     */
    public function complete(User $user, string $nickname, bool $marketingAgreed): SignupResult
    {
        return DB::transaction(function () use ($user, $nickname, $marketingAgreed) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $user->isPending()) {
                return new SignupResult($user, $this->grantSignupPoints($user));
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

            $point = $this->grantSignupPoints($user);

            return new SignupResult($user->refresh(), $point);
        });
    }

    private function grantSignupPoints(User $user): PointResult
    {
        return $this->points->earn($user, PointType::Signup, "signup:{$user->id}");
    }
}
