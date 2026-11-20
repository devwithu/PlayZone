<?php

namespace App\Services\SocialAuth;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Apple REST API 호출 (ADR-003 §5).
 *
 * 앱 로그인 때 받은 authorization_code를 refresh token으로 바꿔 두면,
 * 탈퇴할 때 그 토큰으로 Apple 연결을 폐기(/auth/revoke)할 수 있다.
 */
class AppleTokenClient
{
    private const BASE_URL = 'https://appleid.apple.com';

    /**
     * authorization_code를 refresh token으로 바꾼다. 실패하면 null (로그인은 막지 않는다).
     *
     * @param  string  $clientId  코드를 발급받은 클라이언트 = ID 토큰의 aud (앱 번들 ID)
     */
    public function exchangeAuthorizationCode(string $authorizationCode, string $clientId): ?string
    {
        $clientSecret = $this->clientSecret($clientId);
        if ($clientSecret === null) {
            Log::warning('Apple key is not configured; skipped authorization code exchange');

            return null;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::BASE_URL.'/auth/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
            ]);
        } catch (Throwable $e) {
            Log::warning('Apple authorization code exchange failed', ['error' => $e->getMessage()]);

            return null;
        }

        $refreshToken = $response->json('refresh_token');

        if (! $response->successful() || ! is_string($refreshToken) || $refreshToken === '') {
            Log::warning('Apple authorization code exchange failed', [
                'status' => $response->status(),
                'error' => $response->json('error'),
            ]);

            return null;
        }

        return $refreshToken;
    }

    /**
     * Apple이 요구하는 client_secret: .p8 키로 서명한 ES256 JWT (최대 6개월, 여기서는 5분).
     */
    private function clientSecret(string $clientId): ?string
    {
        $teamId = config('services.apple.team_id');
        $keyId = config('services.apple.key_id');
        $privateKey = $this->privateKey();

        if (! is_string($teamId) || $teamId === '' || ! is_string($keyId) || $keyId === '' || $privateKey === null) {
            return null;
        }

        $now = time();

        return JWT::encode([
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + 300,
            'aud' => self::BASE_URL,
            'sub' => $clientId,
        ], $privateKey, 'ES256', $keyId);
    }

    private function privateKey(): ?string
    {
        $value = config('services.apple.private_key');

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_contains($value, '-----BEGIN')) {
            return $value;
        }

        $path = str_starts_with($value, '/') ? $value : base_path($value);

        if (! is_readable($path)) {
            Log::warning('Apple private key file is not readable', ['path' => $value]);

            return null;
        }

        return (string) file_get_contents($path);
    }
}
