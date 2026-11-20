<?php

namespace App\Services\SocialAuth;

use App\Enums\ApiErrorCode;
use App\Enums\LoginProvider;
use App\Exceptions\ApiException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * 앱이 네이티브 SDK로 받은 Google·Apple ID 토큰을 검증한다 (ADR-003 §3, api-spec §2).
 *
 * 서명(제공자 공개키 JWKS), 발급자(iss), 대상(aud), 만료(exp)를 확인한다.
 * 검증에 실패하면 401 SOCIAL_TOKEN_INVALID. 공개키를 받아오지 못하면 서버 문제이므로 500이다.
 */
class IdTokenVerifier
{
    /** 서버·단말 시계 차이 허용 (초) */
    private const LEEWAY_SECONDS = 60;

    private const JWKS_CACHE_SECONDS = 3600;

    public function verify(LoginProvider $provider, string $idToken): SocialIdentity
    {
        $audiences = $this->audiences($provider);
        if ($audiences === []) {
            Log::warning('ID token audiences are not configured', ['provider' => $provider->value]);

            throw $this->invalid();
        }

        $claims = $this->decode($provider, $idToken);

        if (! in_array($claims['iss'] ?? null, $this->issuers($provider), true)) {
            throw $this->invalid();
        }

        $tokenAudiences = (array) ($claims['aud'] ?? []);
        $audience = collect($tokenAudiences)->first(fn ($aud) => in_array($aud, $audiences, true));
        if (! is_string($audience)) {
            throw $this->invalid();
        }

        $sub = $claims['sub'] ?? null;
        if (! is_string($sub) || $sub === '') {
            throw $this->invalid();
        }

        $email = $claims['email'] ?? null;

        return new SocialIdentity(
            provider: $provider,
            providerUserId: $sub,
            email: is_string($email) && $email !== '' ? $email : null,
            // Apple은 "true" 문자열로 줄 때가 있다
            emailVerified: filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            audience: $audience,
        );
    }

    /**
     * 서명과 시간(exp, nbf, iat)을 검증하고 클레임을 돌려준다.
     *
     * @return array<string, mixed>
     */
    private function decode(LoginProvider $provider, string $idToken): array
    {
        $kid = $this->keyId($idToken);
        $keys = $this->keys($provider);

        // 제공자가 키를 교체했으면 캐시를 버리고 한 번 더 받아온다
        if (! isset($keys[$kid])) {
            $keys = $this->keys($provider, refresh: true);
        }

        if (! isset($keys[$kid])) {
            throw $this->invalid();
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY_SECONDS;

        try {
            $payload = JWT::decode($idToken, $keys);
        } catch (Throwable) {
            throw $this->invalid();
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        // exp는 라이브러리가 값이 있을 때만 검사하므로 없는 토큰은 거부한다
        if (! isset($payload->exp)) {
            throw $this->invalid();
        }

        /** @var array<string, mixed> */
        return (array) $payload;
    }

    private function keyId(string $idToken): string
    {
        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $idToken)[0]));
        } catch (Throwable) {
            throw $this->invalid();
        }

        $kid = is_object($header) ? ($header->kid ?? null) : null;
        if (! is_string($kid) || $kid === '') {
            throw $this->invalid();
        }

        return $kid;
    }

    /**
     * @return array<string, Key>
     */
    private function keys(LoginProvider $provider, bool $refresh = false): array
    {
        $cacheKey = 'social-auth:jwks:'.$provider->value;

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        /** @var array<string, mixed> $jwks */
        $jwks = Cache::remember($cacheKey, self::JWKS_CACHE_SECONDS, fn () => $this->fetchJwks($provider));

        return JWK::parseKeySet($jwks, 'RS256');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchJwks(LoginProvider $provider): array
    {
        try {
            $jwks = Http::timeout(5)->retry(2, 200)->get($this->jwksUri($provider))->throw()->json();
        } catch (ConnectionException|RequestException $e) {
            throw new RuntimeException("Failed to fetch {$provider->value} JWKS", previous: $e);
        }

        if (! is_array($jwks) || ! isset($jwks['keys'])) {
            throw new RuntimeException("Invalid {$provider->value} JWKS response");
        }

        return $jwks;
    }

    private function jwksUri(LoginProvider $provider): string
    {
        return match ($provider) {
            LoginProvider::Google => 'https://www.googleapis.com/oauth2/v3/certs',
            LoginProvider::Apple => 'https://appleid.apple.com/auth/keys',
        };
    }

    /**
     * @return list<string>
     */
    private function issuers(LoginProvider $provider): array
    {
        return match ($provider) {
            LoginProvider::Google => ['https://accounts.google.com', 'accounts.google.com'],
            LoginProvider::Apple => ['https://appleid.apple.com'],
        };
    }

    /**
     * @return list<string>
     */
    private function audiences(LoginProvider $provider): array
    {
        /** @var list<string> */
        return config("services.{$provider->value}.id_token_audiences", []);
    }

    private function invalid(): ApiException
    {
        return ApiException::of(ApiErrorCode::SocialTokenInvalid);
    }
}
