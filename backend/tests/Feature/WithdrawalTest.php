<?php

namespace Tests\Feature;

use App\Enums\LoginProvider;
use App\Enums\UserStatus;
use App\Jobs\RevokeAppleToken;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WithdrawnIdentityHash;
use App\Services\WithdrawalService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\FakesSocialIdTokens;
use Tests\TestCase;

/**
 * 회원 탈퇴 (ADR-003 §5, ERD §12, REQ-009·MOB-005): 앱 DELETE /api/v1/auth/me, 웹 DELETE /settings/account
 */
class WithdrawalTest extends TestCase
{
    use FakesSocialIdTokens, RefreshDatabase;

    private function memberWithAccounts(): User
    {
        $user = User::factory()->create(['nickname' => '젤다러버', 'marketing_agreed_at' => now()]);
        $user->roles()->attach(DB::table('roles')->where('slug', 'member')->value('id'));
        SocialAccount::factory()->for($user)->create([
            'provider' => LoginProvider::Google,
            'provider_user_id' => 'google-sub-1',
        ]);
        SocialAccount::factory()->for($user)->create([
            'provider' => LoginProvider::Apple,
            'provider_user_id' => 'apple-sub-1',
            'refresh_token' => 'apple-refresh-token',
            'refresh_token_client_id' => self::APPLE_AUDIENCE,
        ]);

        return $user;
    }

    public function test_app_withdrawal_anonymizes_and_removes_login_methods(): void
    {
        Queue::fake();
        $user = $this->memberWithAccounts();
        $token = $user->createToken('Unity Android')->plainTextToken;
        $user->createToken('Unity iOS');

        $this->withToken($token)->deleteJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('message', '탈퇴되었습니다.');

        $user->refresh();
        $this->assertSame(UserStatus::Withdrawn, $user->status);
        $this->assertNotNull($user->withdrawn_at);
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->marketing_agreed_at);
        $this->assertSame('withdrawn_'.$user->id, $user->nickname);
        $this->assertSame(0, $user->socialAccounts()->count());
        $this->assertSame(0, $user->roles()->count());
        $this->assertSame(0, $user->tokens()->count());

        foreach (['google' => 'google-sub-1', 'apple' => 'apple-sub-1'] as $provider => $sub) {
            $hash = WithdrawnIdentityHash::query()
                ->where('identity_hash', WithdrawnIdentityHash::hashFor($provider, $sub))
                ->sole();
            $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $hash->expires_at->timestamp, 5);
        }
    }

    public function test_token_cannot_be_used_after_withdrawal(): void
    {
        Queue::fake();
        $user = $this->memberWithAccounts();
        $token = $user->createToken('Unity Android')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/auth/me')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_apple_token_revoke_is_queued_only_for_apple_with_refresh_token(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->memberWithAccounts());

        $this->deleteJson('/api/v1/auth/me')->assertOk();

        Queue::assertPushed(RevokeAppleToken::class, 1);
        Queue::assertPushed(RevokeAppleToken::class, fn (RevokeAppleToken $job) => $job->refreshToken === 'apple-refresh-token'
            && $job->clientId === self::APPLE_AUDIENCE);
        $this->assertInstanceOf(ShouldBeEncrypted::class, new RevokeAppleToken('t', 'c'));
    }

    public function test_pending_user_can_withdraw(): void
    {
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertSame(UserStatus::Withdrawn, $user->refresh()->status);
    }

    public function test_withdrawing_twice_changes_nothing(): void
    {
        Queue::fake();
        $user = $this->memberWithAccounts();
        $service = app(WithdrawalService::class);

        $service->withdraw($user);
        $withdrawnAt = $user->refresh()->withdrawn_at;
        $service->withdraw($user);

        $this->assertEquals($withdrawnAt, $user->refresh()->withdrawn_at);
        Queue::assertPushed(RevokeAppleToken::class, 1);
    }

    public function test_web_sessions_of_user_are_deleted(): void
    {
        Queue::fake();
        config(['session.driver' => 'database']);
        $user = $this->memberWithAccounts();
        $other = User::factory()->create();
        foreach ([$user, $other] as $i => $owner) {
            DB::table('sessions')->insert([
                'id' => "session-{$i}",
                'user_id' => $owner->id,
                'payload' => '',
                'last_activity' => time(),
            ]);
        }

        app(WithdrawalService::class)->withdraw($user);

        $this->assertSame([$other->id], DB::table('sessions')->pluck('user_id')->all());
    }

    public function test_same_social_account_cannot_rejoin_for_30_days(): void
    {
        Queue::fake();
        $this->configureSocialAudiences();
        $this->fakeJwks();
        $user = $this->memberWithAccounts();
        app(WithdrawalService::class)->withdraw($user);

        $this->postJson('/api/v1/auth/social/google', ['id_token' => $this->googleIdToken(), 'device_name' => 'Unity Android'])
            ->assertForbidden()
            ->assertJsonPath('code', 'REJOIN_RESTRICTED');

        $this->travel(31)->days();

        $this->postJson('/api/v1/auth/social/google', ['id_token' => $this->googleIdToken(), 'device_name' => 'Unity Android'])
            ->assertOk()
            ->assertJsonPath('data.signup_completed', false);

        $this->assertNotSame($user->id, SocialAccount::query()->sole()->user_id);
    }

    public function test_withdrawn_nickname_prefix_cannot_be_used(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->postJson('/api/v1/auth/signup/complete', [
            'nickname' => 'Withdrawn_1',
            'age_confirmed' => true,
            'terms' => true,
            'privacy' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nickname.0', '사용할 수 없는 닉네임입니다.');
    }

    public function test_web_withdrawal_requires_confirmation(): void
    {
        $user = $this->memberWithAccounts();

        $this->actingAs($user)
            ->from('/settings/profile')
            ->delete('/settings/account')
            ->assertRedirect('/settings/profile')
            ->assertSessionHasErrors('confirm');

        $this->assertSame(UserStatus::Active, $user->refresh()->status);
    }

    public function test_web_withdrawal_logs_out_and_redirects_home(): void
    {
        Queue::fake();
        $user = $this->memberWithAccounts();

        $this->actingAs($user)
            ->delete('/settings/account', ['confirm' => true])
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertSame(UserStatus::Withdrawn, $user->refresh()->status);
    }

    public function test_guest_cannot_withdraw_on_web(): void
    {
        $this->delete('/settings/account', ['confirm' => true])->assertRedirect(route('login'));
    }

    public function test_revoke_job_calls_apple_revoke_endpoint(): void
    {
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $privatePem);
        config([
            'services.apple.team_id' => 'TEAM123456',
            'services.apple.key_id' => 'KEY1234567',
            'services.apple.private_key' => $privatePem,
        ]);
        Http::fake(['appleid.apple.com/auth/revoke' => Http::response()]);

        RevokeAppleToken::dispatchSync('apple-refresh-token', self::APPLE_AUDIENCE);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://appleid.apple.com/auth/revoke'
            && $request['token'] === 'apple-refresh-token'
            && $request['client_id'] === self::APPLE_AUDIENCE
            && $request['token_type_hint'] === 'refresh_token'
            && is_string($request['client_secret']));
    }

    public function test_revoke_job_fails_for_retry_when_apple_returns_error(): void
    {
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $privatePem);
        config([
            'services.apple.team_id' => 'TEAM123456',
            'services.apple.key_id' => 'KEY1234567',
            'services.apple.private_key' => $privatePem,
        ]);
        Http::fake(['appleid.apple.com/auth/revoke' => Http::response(['error' => 'invalid_client'], 400)]);

        $this->expectException(RuntimeException::class);

        RevokeAppleToken::dispatchSync('apple-refresh-token', self::APPLE_AUDIENCE);
    }

    public function test_revoke_job_skips_when_apple_key_is_not_configured(): void
    {
        Http::fake();

        RevokeAppleToken::dispatchSync('apple-refresh-token', self::APPLE_AUDIENCE);

        Http::assertNothingSent();
    }

    public function test_withdrawal_succeeds_even_if_apple_revoke_fails(): void
    {
        // 큐가 sync여도 revoke 실패가 탈퇴를 되돌리지 않는지 확인
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $privatePem);
        config([
            'services.apple.team_id' => 'TEAM123456',
            'services.apple.key_id' => 'KEY1234567',
            'services.apple.private_key' => $privatePem,
        ]);
        Http::fake(['appleid.apple.com/auth/revoke' => Http::response(['error' => 'server_error'], 500)]);
        $user = $this->memberWithAccounts();

        try {
            app(WithdrawalService::class)->withdraw($user);
        } catch (RuntimeException) {
            // sync 큐에서는 작업 예외가 호출한 쪽으로 올라온다. 실제 Redis 큐에서는 재시도된다.
        }

        $this->assertSame(UserStatus::Withdrawn, $user->refresh()->status);
    }
}
