<?php

namespace App\Services;

use App\Enums\LoginProvider;
use App\Enums\UserStatus;
use App\Jobs\RevokeAppleToken;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WithdrawnIdentityHash;
use Illuminate\Support\Facades\DB;

/**
 * 회원 탈퇴 (ADR-003 §5, ERD §12, REQ-009·MOB-005). 웹과 앱이 함께 쓴다.
 *
 * - 30일 재가입 제한: 소셜 계정 식별자의 해시를 남긴다
 * - 익명화: 이메일 삭제, 닉네임 withdrawn_{id}. users 행은 리뷰·원장 FK 때문에 남긴다
 * - 소셜 계정·역할·관심 게임 삭제(관심 수 집계도 줄임), 모든 앱 토큰과 웹 세션 폐기
 * - Apple 토큰 폐기는 커밋 뒤 큐 작업으로 (실패해도 탈퇴는 유지)
 *
 * Google은 저장해 둔 Google 토큰이 없어 폐기 호출을 하지 않는다 (ADR-003 §5).
 * 포인트 잔액 소멸(withdraw_expire 원장, 포인트 정책 §10)은 3단계에서 PointService를 만들 때 여기서 처리한다.
 */
class WithdrawalService
{
    public const REJOIN_RESTRICTION_DAYS = 30;

    public function __construct(private readonly FavoriteService $favorites) {}

    public function withdraw(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->status === UserStatus::Withdrawn) {
                return;
            }

            $now = now();
            $accounts = $user->socialAccounts()->get();

            foreach ($accounts as $account) {
                WithdrawnIdentityHash::query()->updateOrCreate(
                    ['identity_hash' => WithdrawnIdentityHash::hashFor($account->provider->value, $account->provider_user_id)],
                    ['expires_at' => $now->addDays(self::REJOIN_RESTRICTION_DAYS)],
                );

                $this->revokeProviderToken($account);
            }

            $user->socialAccounts()->delete();
            $user->roles()->detach();
            $this->favorites->removeAllFor($user);
            $user->tokens()->delete();
            $this->deleteWebSessions($user);

            $user->forceFill([
                'email' => null,
                'email_verified_at' => null,
                'nickname' => 'withdrawn_'.$user->id,
                'status' => UserStatus::Withdrawn,
                'marketing_agreed_at' => null,
                'withdrawn_at' => $now,
                'remember_token' => null,
            ])->save();
        });
    }

    private function revokeProviderToken(SocialAccount $account): void
    {
        if ($account->provider !== LoginProvider::Apple
            || $account->refresh_token === null
            || $account->refresh_token_client_id === null) {
            return;
        }

        RevokeAppleToken::dispatch($account->refresh_token, $account->refresh_token_client_id)->afterCommit();
    }

    /**
     * 다른 기기의 웹 로그인도 끊는다 (세션을 DB에 저장할 때만 가능).
     */
    private function deleteWebSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
    }
}
