<?php

namespace App\Services;

use App\Enums\LoginProvider;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\SocialAuth\AppleTokenClient;
use App\Services\SocialAuth\IdTokenVerifier;
use Illuminate\Support\Facades\DB;

/**
 * Unity 앱 소셜 로그인 (ADR-003 §3, api-spec §7.1 POST /auth/social/{provider}).
 *
 * ID 토큰 검증 → 회원 찾기·만들기(웹과 같은 SocialLoginService) → Sanctum 토큰 발급.
 */
class AppLoginService
{
    public function __construct(
        private readonly IdTokenVerifier $verifier,
        private readonly AppleTokenClient $apple,
        private readonly SocialLoginService $socialLogin,
    ) {}

    /**
     * @return array{user: User, token: string}
     *
     * @throws ApiException SOCIAL_TOKEN_INVALID, ACCOUNT_SUSPENDED, REJOIN_RESTRICTED
     */
    public function login(LoginProvider $provider, string $idToken, ?string $authorizationCode, string $deviceName): array
    {
        $identity = $this->verifier->verify($provider, $idToken);

        // Apple만: 탈퇴 시 토큰 폐기용 refresh token (외부 호출이라 DB 트랜잭션 밖에서)
        $refreshToken = $provider === LoginProvider::Apple && $authorizationCode !== null
            ? $this->apple->exchangeAuthorizationCode($authorizationCode, $identity->audience)
            : null;

        $user = $this->socialLogin->findOrCreateUser(
            provider: $provider,
            providerUserId: $identity->providerUserId,
            email: $identity->email,
            emailVerified: $identity->emailVerified,
            refreshToken: $refreshToken,
            // 앱 로그인 토큰은 ID 토큰의 aud(앱 번들 ID)로 발급된다
            refreshTokenClientId: $refreshToken !== null ? $identity->audience : null,
        );

        // 같은 기기 이름으로 다시 로그인하면 이전 토큰을 폐기한다 (api-spec §2)
        $token = DB::transaction(function () use ($user, $deviceName) {
            $user->tokens()->where('name', $deviceName)->delete();

            return $user->createToken($deviceName)->plainTextToken;
        });

        return ['user' => $user, 'token' => $token];
    }
}
