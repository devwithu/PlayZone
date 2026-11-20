<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 내 정보 (api-spec §7.1 GET /auth/me).
 *
 * 등급(user_levels)·제재(user_sanctions)는 3단계에서 테이블을 만들 때 추가한다.
 * 이메일은 응답하지 않는다 (2026-11-19 결정: 화면에 이메일을 보내지 않음).
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            /** pending: 가입 미완료, active: 정상, suspended: 정지 */
            'status' => $this->status,
            'signup_completed' => ! $this->isPending(),
            'point_balance' => $this->point_balance,
            'lifetime_points' => $this->lifetime_points,
            'marketing_agreed' => $this->marketing_agreed_at !== null,
            'signup_completed_at' => $this->signup_completed_at?->toIso8601ZuluString(),
            /** 연결된 소셜 계정 */
            'social_accounts' => $this->socialAccounts
                ->sortBy('id')
                ->values()
                ->map(fn (SocialAccount $account): array => [
                    'provider' => $account->provider,
                    'connected_at' => $account->created_at?->toIso8601ZuluString(),
                ])
                ->all(),
        ];
    }
}
