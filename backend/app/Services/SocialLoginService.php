<?php

namespace App\Services;

use App\Enums\LoginProvider;
use App\Enums\UserStatus;
use App\Exceptions\LoginDeniedException;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WithdrawnIdentityHash;
use Illuminate\Support\Facades\DB;

/**
 * 소셜 로그인으로 회원을 찾거나 만든다 (ADR-003 §3·§4).
 * 웹(Socialite 리디렉션)과 앱(ID 토큰 검증, 이후 구현)이 함께 쓴다.
 */
class SocialLoginService
{
    /**
     * @throws LoginDeniedException
     */
    public function findOrCreateUser(
        LoginProvider $provider,
        string $providerUserId,
        ?string $email,
        bool $emailVerified,
        ?string $refreshToken = null,
    ): User {
        $email = $email !== null ? mb_strtolower($email) : null;

        return DB::transaction(function () use ($provider, $providerUserId, $email, $emailVerified, $refreshToken) {
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $this->ensureNotRecentlyWithdrawn($provider, $providerUserId);

                $user = new User;
                $user->status = UserStatus::Pending;
                // 다른 회원이 이미 쓰는 이메일이면 회원 이메일은 비워 둔다. 자동으로 합치지 않는다 (ADR-003 §4).
                if ($email !== null && ! User::query()->where('email', $email)->exists()) {
                    $user->email = $email;
                    $user->email_verified_at = $emailVerified ? now() : null;
                }
                $user->save();

                $account = $user->socialAccounts()->make([
                    'provider' => $provider,
                    'provider_user_id' => $providerUserId,
                ]);
            } else {
                $user = $account->user;
            }

            if ($user->status === UserStatus::Suspended) {
                throw LoginDeniedException::suspended();
            }

            // Apple은 이메일을 첫 로그인 때만 주므로, 값이 있을 때만 갱신한다.
            if ($email !== null) {
                $account->email = $email;
            }
            if ($refreshToken !== null) {
                $account->refresh_token = $refreshToken;
            }
            $account->last_login_at = now();
            $account->save();

            return $user;
        });
    }

    /**
     * @throws LoginDeniedException
     */
    private function ensureNotRecentlyWithdrawn(LoginProvider $provider, string $providerUserId): void
    {
        $record = WithdrawnIdentityHash::query()
            ->where('identity_hash', WithdrawnIdentityHash::hashFor($provider->value, $providerUserId))
            ->where('expires_at', '>', now())
            ->first();

        if ($record !== null) {
            throw LoginDeniedException::rejoinRestricted($record->expires_at);
        }
    }
}
