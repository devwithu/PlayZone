<?php

namespace Tests\Feature;

use App\Enums\LoginProvider;
use App\Jobs\RevokeAppleToken;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuth\WebSocialiteDrivers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\Concerns\FakesSocialIdTokens;
use Tests\TestCase;

/**
 * 소셜 계정 연결·해제 (ADR-003 §7-1, REQ-008, api-spec §7.1)
 */
class SocialAccountLinkTest extends TestCase
{
    use FakesSocialIdTokens, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureSocialAudiences();
        $this->fakeJwks();
    }

    private function googleMember(): User
    {
        $user = User::factory()->create();
        SocialAccount::factory()->for($user)->create([
            'provider' => LoginProvider::Google,
            'provider_user_id' => 'google-sub-1',
        ]);

        return $user;
    }

    // ---- 앱 API ----

    public function test_app_links_apple_account(): void
    {
        $user = $this->googleMember();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/social-accounts/apple', ['id_token' => $this->appleIdToken()])
            ->assertOk()
            ->assertJsonPath('message', 'Apple 계정을 연결했습니다.')
            ->assertJsonPath('data.social_accounts.0.provider', 'google')
            ->assertJsonPath('data.social_accounts.1.provider', 'apple');

        $apple = $user->socialAccounts()->where('provider', 'apple')->sole();
        $this->assertSame('apple-sub-1', $apple->provider_user_id);
        $this->assertSame('abc123@privaterelay.appleid.com', $apple->email);
    }

    public function test_linked_account_can_log_in_as_same_user(): void
    {
        $user = $this->googleMember();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/auth/social-accounts/apple', ['id_token' => $this->appleIdToken()])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/social/apple', ['id_token' => $this->appleIdToken(), 'device_name' => 'Unity iOS'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.signup_completed', true);
    }

    public function test_linking_again_with_same_account_is_ok(): void
    {
        $user = $this->googleMember();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/social-accounts/google', ['id_token' => $this->googleIdToken()])->assertOk();

        $this->assertSame(1, $user->socialAccounts()->count());
    }

    public function test_cannot_link_account_used_by_another_member(): void
    {
        $other = User::factory()->create();
        SocialAccount::factory()->for($other)->create(['provider' => LoginProvider::Apple, 'provider_user_id' => 'apple-sub-1']);
        Sanctum::actingAs($this->googleMember());

        $this->postJson('/api/v1/auth/social-accounts/apple', ['id_token' => $this->appleIdToken()])
            ->assertConflict()
            ->assertJsonPath('code', 'SOCIAL_ACCOUNT_IN_USE');

        $this->assertSame($other->id, SocialAccount::query()->where('provider_user_id', 'apple-sub-1')->value('user_id'));
    }

    public function test_cannot_link_second_account_of_same_provider(): void
    {
        Sanctum::actingAs($this->googleMember());

        $this->postJson('/api/v1/auth/social-accounts/google', ['id_token' => $this->googleIdToken(['sub' => 'google-sub-2'])])
            ->assertConflict()
            ->assertJsonPath('code', 'SOCIAL_PROVIDER_ALREADY_LINKED');
    }

    public function test_invalid_id_token_cannot_be_linked(): void
    {
        Sanctum::actingAs($this->googleMember());

        $this->postJson('/api/v1/auth/social-accounts/apple', ['id_token' => $this->appleIdToken(['aud' => 'other.app'])])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SOCIAL_TOKEN_INVALID');
    }

    public function test_pending_member_cannot_link(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->postJson('/api/v1/auth/social-accounts/apple', ['id_token' => $this->appleIdToken()])
            ->assertForbidden()
            ->assertJsonPath('code', 'SIGNUP_INCOMPLETE');
    }

    public function test_app_unlinks_account_and_revokes_apple_token(): void
    {
        Queue::fake();
        $user = $this->googleMember();
        SocialAccount::factory()->for($user)->create([
            'provider' => LoginProvider::Apple,
            'provider_user_id' => 'apple-sub-1',
            'refresh_token' => 'apple-refresh-token',
            'refresh_token_client_id' => self::APPLE_AUDIENCE,
        ]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/auth/social-accounts/apple')
            ->assertOk()
            ->assertJsonPath('message', 'Apple 계정 연결을 해제했습니다.')
            ->assertJsonCount(1, 'data.social_accounts');

        Queue::assertPushed(RevokeAppleToken::class, fn (RevokeAppleToken $job) => $job->refreshToken === 'apple-refresh-token');
    }

    public function test_cannot_unlink_last_login_method(): void
    {
        Sanctum::actingAs($this->googleMember());

        $this->deleteJson('/api/v1/auth/social-accounts/google')
            ->assertConflict()
            ->assertJsonPath('code', 'LAST_LOGIN_METHOD');
    }

    public function test_unlinking_not_linked_provider_is_not_found(): void
    {
        Sanctum::actingAs($this->googleMember());

        $this->deleteJson('/api/v1/auth/social-accounts/apple')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    // ---- 웹 ----

    public function test_profile_page_shows_login_connections(): void
    {
        $this->actingAs($this->googleMember())
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('socialAccounts.0', ['id' => 'google', 'label' => 'Google', 'connected' => true])
                ->where('socialAccounts.1', ['id' => 'apple', 'label' => 'Apple', 'connected' => false]));
    }

    public function test_web_link_redirects_to_provider_and_remembers_intent(): void
    {
        Socialite::fake('apple');

        $this->actingAs($this->googleMember())
            ->get(route('social-accounts.link', 'apple'))
            ->assertRedirect()
            ->assertSessionHas('social_link', 'apple');
    }

    public function test_web_callback_links_account_for_logged_in_member(): void
    {
        $user = $this->googleMember();
        Socialite::fake('apple', (new SocialiteUser)->map(['id' => 'apple-sub-9', 'email' => null]));

        $this->actingAs($user)
            ->withSession(['social_link' => 'apple'])
            ->get('/auth/apple/callback')
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('socialAccountMessage', ['type' => 'success', 'message' => 'Apple 계정을 연결했습니다.']);

        $this->assertSame('apple-sub-9', $user->socialAccounts()->where('provider', 'apple')->value('provider_user_id'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_callback_shows_error_when_account_is_in_use(): void
    {
        $other = User::factory()->create();
        SocialAccount::factory()->for($other)->create(['provider' => LoginProvider::Apple, 'provider_user_id' => 'apple-sub-9']);
        $user = $this->googleMember();
        Socialite::fake('apple', (new SocialiteUser)->map(['id' => 'apple-sub-9']));

        $this->actingAs($user)
            ->withSession(['social_link' => 'apple'])
            ->get('/auth/apple/callback')
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('socialAccountMessage.type', 'error');

        $this->assertSame(1, $user->socialAccounts()->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_web_unlink(): void
    {
        Queue::fake();
        $user = $this->googleMember();
        SocialAccount::factory()->for($user)->create(['provider' => LoginProvider::Apple]);

        $this->actingAs($user)
            ->delete(route('social-accounts.destroy', 'apple'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('socialAccountMessage.type', 'success');

        $this->assertSame(['google'], $user->socialAccounts()->pluck('provider')->map->value->all());
    }

    public function test_web_cannot_unlink_last_login_method(): void
    {
        $user = $this->googleMember();

        $this->actingAs($user)
            ->delete(route('social-accounts.destroy', 'google'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('socialAccountMessage', ['type' => 'error', 'message' => '마지막 로그인 수단은 연결을 해제할 수 없습니다.']);

        $this->assertSame(1, $user->socialAccounts()->count());
    }

    // ---- Apple 웹 드라이버 설정 ----

    private function configureAppleWeb(): void
    {
        config([
            'services.apple.client_id' => 'com.example.playzone.web',
            'services.apple.client_secret' => 'unused-in-redirect',
            'services.apple.redirect' => 'https://playzone.test/auth/apple/callback',
        ]);
    }

    public function test_apple_login_redirect_is_stateless_with_nonce_cookie(): void
    {
        $this->configureAppleWeb();

        $response = $this->get(route('social.redirect', 'apple'));

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('response_mode=form_post', $location);
        $this->assertStringContainsString('nonce=', $location);
        $response->assertCookie(WebSocialiteDrivers::APPLE_NONCE_COOKIE, encrypted: false);
    }

    public function test_apple_link_redirect_uses_query_response_without_scope(): void
    {
        $this->configureAppleWeb();

        $response = $this->actingAs($this->googleMember())->get(route('social-accounts.link', 'apple'));

        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('response_mode=query', $location);
        $this->assertStringContainsString('state=', $location);
        $this->assertStringNotContainsString('scope=name', $location);
    }
}
