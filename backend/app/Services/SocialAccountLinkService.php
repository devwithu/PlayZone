<?php

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Enums\LoginProvider;
use App\Exceptions\ApiException;
use App\Jobs\RevokeAppleToken;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\AppleTokenClient;
use App\Services\SocialAuth\IdTokenVerifier;
use Illuminate\Support\Facades\DB;

/**
 * 로그인한 회원에게 다른 소셜 계정을 연결하거나 해제한다 (ADR-003 §7-1, REQ-008).
 * 자동으로 합치지 않고, 회원이 직접 연결한 경우에만 하나의 회원이 여러 로그인 수단을 가진다.
 */
class SocialAccountLinkService
{
    public function __construct(
        private readonly IdTokenVerifier $verifier,
        private readonly AppleTokenClient $apple,
    ) {}

    /**
     * 앱: 네이티브 SDK로 받은 ID 토큰으로 연결한다.
     *
     * @throws ApiException SOCIAL_TOKEN_INVALID, SOCIAL_ACCOUNT_IN_USE, SOCIAL_PROVIDER_ALREADY_LINKED
     */
    public function linkWithIdToken(User $user, LoginProvider $provider, string $idToken, ?string $authorizationCode): SocialAccount
    {
        $identity = $this->verifier->verify($provider, $idToken);

        $refreshToken = $provider === LoginProvider::Apple && $authorizationCode !== null
            ? $this->apple->exchangeAuthorizationCode($authorizationCode, $identity->audience)
            : null;

        return $this->link(
            user: $user,
            provider: $provider,
            providerUserId: $identity->providerUserId,
            email: $identity->email,
            refreshToken: $refreshToken,
            refreshTokenClientId: $refreshToken !== null ? $identity->audience : null,
        );
    }

    /**
     * @throws ApiException SOCIAL_ACCOUNT_IN_USE, SOCIAL_PROVIDER_ALREADY_LINKED
     */
    public function link(
        User $user,
        LoginProvider $provider,
        string $providerUserId,
        ?string $email,
        ?string $refreshToken = null,
        ?string $refreshTokenClientId = null,
    ): SocialAccount {
        return DB::transaction(function () use ($user, $provider, $providerUserId, $email, $refreshToken, $refreshTokenClientId) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($account !== null && $account->user_id !== $user->id) {
                throw ApiException::of(ApiErrorCode::SocialAccountInUse);
            }

            if ($account === null) {
                if ($user->socialAccounts()->where('provider', $provider)->exists()) {
                    throw ApiException::of(ApiErrorCode::SocialProviderAlreadyLinked);
                }

                $account = $user->socialAccounts()->make([
                    'provider' => $provider,
                    'provider_user_id' => $providerUserId,
                ]);
            }

            if ($email !== null) {
                $account->email = mb_strtolower($email);
            }
            if ($refreshToken !== null) {
                $account->refresh_token = $refreshToken;
                $account->refresh_token_client_id = $refreshTokenClientId;
            }
            $account->save();

            return $account;
        });
    }

    /**
     * 연결을 해제한다. 마지막 로그인 수단은 해제할 수 없다.
     * Apple은 Apple 쪽 연결도 폐기한다 (커밋 뒤 큐 작업).
     *
     * @throws ApiException NOT_FOUND, LAST_LOGIN_METHOD
     */
    public function unlink(User $user, LoginProvider $provider): void
    {
        DB::transaction(function () use ($user, $provider) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $accounts = $user->socialAccounts()->get();
            $account = $accounts->first(fn (SocialAccount $account) => $account->provider === $provider);

            if ($account === null) {
                throw ApiException::of(ApiErrorCode::NotFound, '연결되지 않은 계정입니다.');
            }

            // 이메일·비밀번호 가입을 추가하면 비밀번호도 로그인 수단으로 센다 (ADR-003 §8)
            if ($accounts->count() <= 1) {
                throw ApiException::of(ApiErrorCode::LastLoginMethod);
            }

            $account->delete();

            if ($provider === LoginProvider::Apple
                && $account->refresh_token !== null
                && $account->refresh_token_client_id !== null) {
                RevokeAppleToken::dispatch($account->refresh_token, $account->refresh_token_client_id)->afterCommit();
            }
        });
    }
}
