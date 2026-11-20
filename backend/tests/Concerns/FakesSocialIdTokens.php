<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

/**
 * 실제 Google·Apple 키 없이 앱 로그인을 테스트하기 위한 도구.
 * 테스트용 RSA 키로 ID 토큰을 서명하고, 제공자 JWKS 주소를 Http::fake로 그 공개키를 돌려주게 한다.
 */
trait FakesSocialIdTokens
{
    public const GOOGLE_AUDIENCE = 'test-web-client.apps.googleusercontent.com';

    public const APPLE_AUDIENCE = 'com.example.playzone';

    /** @var array<string, OpenSSLAsymmetricKey> */
    private static array $rsaKeys = [];

    protected function configureSocialAudiences(): void
    {
        config([
            'services.google.id_token_audiences' => [self::GOOGLE_AUDIENCE],
            'services.apple.id_token_audiences' => [self::APPLE_AUDIENCE],
        ]);
    }

    protected function rsaKey(string $kid): OpenSSLAsymmetricKey
    {
        return self::$rsaKeys[$kid] ??= openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function jwk(string $kid): array
    {
        $details = openssl_pkey_get_details($this->rsaKey($kid));

        return [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }

    /**
     * 두 제공자의 JWKS 주소가 주어진 키들을 돌려주게 한다.
     *
     * @param  list<string>  $kids
     */
    protected function fakeJwks(array $kids = ['test-key'], array $extra = []): void
    {
        $body = ['keys' => array_map(fn (string $kid) => $this->jwk($kid), $kids)];

        $this->fakeHttp([
            'www.googleapis.com/oauth2/v3/certs' => Http::response($body),
            'appleid.apple.com/auth/keys' => Http::response($body),
            ...$extra,
        ]);
    }

    /**
     * 앞서 등록한 가짜 응답을 버리고 새로 등록한다.
     * (Http::fake를 여러 번 부르면 먼저 등록한 응답이 우선한다)
     *
     * @param  array<string, mixed>  $stubs
     */
    protected function fakeHttp(array $stubs): void
    {
        Http::swap(new HttpFactory);
        Http::fake($stubs);
    }

    /**
     * @param  array<string, mixed>  $claims  기본 클레임을 덮어쓴다 (값이 null이면 클레임을 뺀다)
     */
    protected function googleIdToken(array $claims = [], string $kid = 'test-key'): string
    {
        return $this->idToken([
            'iss' => 'https://accounts.google.com',
            'aud' => self::GOOGLE_AUDIENCE,
            'sub' => 'google-sub-1',
            'email' => 'player@gmail.com',
            'email_verified' => true,
            ...$claims,
        ], $kid);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function appleIdToken(array $claims = [], string $kid = 'test-key'): string
    {
        return $this->idToken([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::APPLE_AUDIENCE,
            'sub' => 'apple-sub-1',
            'email' => 'abc123@privaterelay.appleid.com',
            'email_verified' => 'true',
            ...$claims,
        ], $kid);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function idToken(array $claims, string $kid = 'test-key', ?OpenSSLAsymmetricKey $signWith = null): string
    {
        $now = time();
        $payload = array_filter(
            ['iat' => $now, 'exp' => $now + 3600, ...$claims],
            fn ($value) => $value !== null,
        );

        openssl_pkey_export($signWith ?? $this->rsaKey($kid), $pem);

        return JWT::encode($payload, $pem, 'RS256', $kid);
    }
}
