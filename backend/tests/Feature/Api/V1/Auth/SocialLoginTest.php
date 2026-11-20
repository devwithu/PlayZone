<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\LoginProvider;
use App\Enums\UserStatus;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WithdrawnIdentityHash;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\FakesSocialIdTokens;
use Tests\TestCase;

/**
 * POST /api/v1/auth/social/{provider} (api-spec §7.1, ADR-003 §3)
 */
class SocialLoginTest extends TestCase
{
    use FakesSocialIdTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSocialAudiences();
        $this->fakeJwks();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function login(string $provider, array $body): TestResponse
    {
        return $this->postJson("/api/v1/auth/social/{$provider}", ['device_name' => 'Unity Android', ...$body]);
    }

    public function test_first_google_login_creates_pending_user_and_issues_token(): void
    {
        $response = $this->login('google', ['id_token' => $this->googleIdToken()])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '로그인되었습니다.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.signup_completed', false)
            ->assertJsonPath('data.user.nickname', null);

        $user = User::query()->sole();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('player@gmail.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $response->assertJsonPath('data.user.id', $user->id);

        $account = SocialAccount::query()->sole();
        $this->assertSame(LoginProvider::Google, $account->provider);
        $this->assertSame('google-sub-1', $account->provider_user_id);

        $token = $user->tokens()->sole();
        $this->assertSame('Unity Android', $token->name);
        $this->assertStringEndsWith(
            hash('sha256', explode('|', $response->json('data.access_token'))[1]),
            $token->token,
        );
    }

    public function test_existing_active_user_logs_in_with_signup_completed(): void
    {
        $user = User::factory()->create(['nickname' => '젤다러버']);
        SocialAccount::factory()->for($user)->create([
            'provider' => LoginProvider::Google,
            'provider_user_id' => 'google-sub-1',
        ]);

        $this->login('google', ['id_token' => $this->googleIdToken()])
            ->assertOk()
            ->assertJsonPath('data.signup_completed', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.nickname', '젤다러버');

        $this->assertSame(1, User::query()->count());
    }

    public function test_login_again_with_same_device_revokes_previous_token(): void
    {
        $this->login('google', ['id_token' => $this->googleIdToken()])->assertOk();
        $this->login('google', ['id_token' => $this->googleIdToken(), 'device_name' => 'Unity iOS'])->assertOk();
        $second = $this->login('google', ['id_token' => $this->googleIdToken()])->assertOk();

        $user = User::query()->sole();
        $this->assertEqualsCanonicalizing(
            ['Unity Android', 'Unity iOS'],
            $user->tokens()->pluck('name')->all(),
        );

        $latest = $user->tokens()->where('name', 'Unity Android')->sole();
        $this->assertSame(hash('sha256', explode('|', $second->json('data.access_token'))[1]), $latest->token);
    }

    public function test_uses_email_from_token_not_from_request(): void
    {
        $this->login('google', [
            'id_token' => $this->googleIdToken(['email' => 'real@gmail.com']),
            'email' => 'fake@example.com',
        ])->assertOk();

        $this->assertSame('real@gmail.com', SocialAccount::query()->sole()->email);
    }

    public function test_apple_login_accepts_string_email_verified(): void
    {
        $this->login('apple', ['id_token' => $this->appleIdToken()])->assertOk();

        $user = User::query()->sole();
        $this->assertSame('abc123@privaterelay.appleid.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(LoginProvider::Apple, SocialAccount::query()->sole()->provider);
    }

    public function test_apple_authorization_code_is_exchanged_for_refresh_token(): void
    {
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $privatePem);
        $publicPem = openssl_pkey_get_details($ecKey)['key'];

        config([
            'services.apple.team_id' => 'TEAM123456',
            'services.apple.key_id' => 'KEY1234567',
            'services.apple.private_key' => $privatePem,
        ]);

        $this->fakeJwks(extra: [
            'appleid.apple.com/auth/token' => Http::response(['refresh_token' => 'apple-refresh-token']),
        ]);

        $this->login('apple', [
            'id_token' => $this->appleIdToken(),
            'authorization_code' => 'code-from-app',
        ])->assertOk();

        $account = SocialAccount::query()->sole();
        $this->assertSame('apple-refresh-token', $account->refresh_token);
        $this->assertSame(self::APPLE_AUDIENCE, $account->refresh_token_client_id);
        // DB에는 암호화되어 저장된다
        $this->assertNotSame('apple-refresh-token', DB::table('social_accounts')->value('refresh_token'));

        Http::assertSent(function (HttpRequest $request) use ($publicPem) {
            if ($request->url() !== 'https://appleid.apple.com/auth/token') {
                return false;
            }

            $secret = JWT::decode($request['client_secret'], new Key($publicPem, 'ES256'));

            return $request['client_id'] === self::APPLE_AUDIENCE
                && $request['code'] === 'code-from-app'
                && $request['grant_type'] === 'authorization_code'
                && $secret->iss === 'TEAM123456'
                && $secret->sub === self::APPLE_AUDIENCE
                && $secret->aud === 'https://appleid.apple.com';
        });
    }

    public function test_apple_login_succeeds_even_if_code_exchange_fails(): void
    {
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $privatePem);
        config([
            'services.apple.team_id' => 'TEAM123456',
            'services.apple.key_id' => 'KEY1234567',
            'services.apple.private_key' => $privatePem,
        ]);

        $this->fakeJwks(extra: [
            'appleid.apple.com/auth/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $this->login('apple', ['id_token' => $this->appleIdToken(), 'authorization_code' => 'expired-code'])
            ->assertOk();

        $this->assertNull(SocialAccount::query()->sole()->refresh_token);
    }

    public function test_apple_code_is_not_exchanged_when_key_is_not_configured(): void
    {
        $this->login('apple', ['id_token' => $this->appleIdToken(), 'authorization_code' => 'code'])
            ->assertOk();

        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), '/auth/token'));
    }

    public function test_google_ignores_authorization_code(): void
    {
        $this->login('google', ['id_token' => $this->googleIdToken(), 'authorization_code' => 'code'])
            ->assertOk();

        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), '/auth/token'));
    }

    /**
     * @return array<string, array{0: \Closure(self): string}>
     */
    public static function invalidTokens(): array
    {
        return [
            'signed by another key' => [fn (self $t) => $t->idToken(
                ['iss' => 'https://accounts.google.com', 'aud' => self::GOOGLE_AUDIENCE, 'sub' => 'x'],
                'test-key',
                openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]),
            )],
            'wrong audience' => [fn (self $t) => $t->googleIdToken(['aud' => 'someone-else.apps.googleusercontent.com'])],
            'wrong issuer' => [fn (self $t) => $t->googleIdToken(['iss' => 'https://evil.example.com'])],
            'apple token sent to google' => [fn (self $t) => $t->appleIdToken()],
            'expired' => [fn (self $t) => $t->googleIdToken(['iat' => time() - 7200, 'exp' => time() - 3600])],
            'no exp' => [fn (self $t) => $t->googleIdToken(['exp' => null])],
            'no sub' => [fn (self $t) => $t->googleIdToken(['sub' => null])],
            'unknown key id' => [fn (self $t) => $t->googleIdToken([], 'unknown-key')],
            'not a jwt' => [fn (self $t) => 'not-a-jwt'],
        ];
    }

    /**
     * @param  \Closure(self): string  $makeToken
     */
    #[DataProvider('invalidTokens')]
    public function test_invalid_id_token_is_rejected(\Closure $makeToken): void
    {
        $this->login('google', ['id_token' => $makeToken($this)])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SOCIAL_TOKEN_INVALID');

        $this->assertSame(0, User::query()->count());
    }

    public function test_rejects_all_tokens_when_audiences_are_not_configured(): void
    {
        config(['services.google.id_token_audiences' => []]);

        $this->login('google', ['id_token' => $this->googleIdToken()])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SOCIAL_TOKEN_INVALID');
    }

    public function test_refetches_jwks_when_provider_rotates_keys(): void
    {
        $this->fakeHttp([
            'www.googleapis.com/oauth2/v3/certs' => Http::sequence()
                ->push(['keys' => [$this->jwk('old-key')]])
                ->push(['keys' => [$this->jwk('old-key'), $this->jwk('new-key')]]),
        ]);

        $this->login('google', ['id_token' => $this->googleIdToken([], 'old-key')])->assertOk();
        $this->login('google', ['id_token' => $this->googleIdToken([], 'new-key')])->assertOk();

        Http::assertSentCount(2);
    }

    public function test_jwks_fetch_failure_is_server_error(): void
    {
        $this->fakeHttp(['www.googleapis.com/*' => Http::response('down', 503)]);

        $this->login('google', ['id_token' => $this->googleIdToken()])
            ->assertInternalServerError()
            ->assertJsonPath('code', 'SERVER_ERROR');
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->suspended()->create();
        SocialAccount::factory()->for($user)->create(['provider_user_id' => 'google-sub-1']);

        $this->login('google', ['id_token' => $this->googleIdToken()])
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_SUSPENDED');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_recently_withdrawn_account_cannot_rejoin(): void
    {
        WithdrawnIdentityHash::create([
            'identity_hash' => WithdrawnIdentityHash::hashFor('google', 'google-sub-1'),
            'expires_at' => now()->addDays(10),
        ]);

        $this->login('google', ['id_token' => $this->googleIdToken()])
            ->assertForbidden()
            ->assertJsonPath('code', 'REJOIN_RESTRICTED');
    }

    public function test_requires_id_token_and_device_name(): void
    {
        $this->postJson('/api/v1/auth/social/google', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['id_token', 'device_name']]);
    }

    public function test_unknown_provider_is_not_found(): void
    {
        $this->login('kakao', ['id_token' => 'x'])
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_is_rate_limited_to_10_per_minute_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/social/google', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/social/google', [])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'RATE_LIMITED');
    }
}
