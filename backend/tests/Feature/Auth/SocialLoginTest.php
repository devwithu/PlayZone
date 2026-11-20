<?php

namespace Tests\Feature\Auth;

use App\Enums\LoginProvider;
use App\Enums\UserStatus;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\WithdrawnIdentityHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSocialUser(
        string $provider,
        string $id,
        ?string $email = 'player@example.com',
        ?string $refreshToken = null,
    ): void {
        $user = (new SocialiteUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => true])
            ->map(['id' => $id, 'email' => $email]);
        $user->refreshToken = $refreshToken;

        Socialite::fake($provider, $user);
    }

    public function test_login_page_shows_google_and_apple(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('providers.0.id', 'google')
                ->where('providers.1.id', 'apple'));
    }

    public function test_redirect_sends_user_to_provider(): void
    {
        Socialite::fake('google');

        $this->get(route('social.redirect', 'google'))->assertRedirect();
    }

    public function test_redirect_from_other_host_moves_to_app_url_first(): void
    {
        // 127.0.0.1에서 시작하면 localhost로 돌아오는 콜백에 세션 쿠키가 없어 state 검증이 실패한다
        config(['app.url' => 'http://localhost:8000']);
        Socialite::fake('google');

        $this->get('http://127.0.0.1:8000/auth/google/redirect')
            ->assertRedirect('http://localhost:8000/auth/google/redirect');

        // APP_URL 주소에서 시작하면 바로 제공자로 보낸다
        $location = (string) $this->get('http://localhost:8000/auth/google/redirect')->headers->get('Location');
        $this->assertStringStartsNotWith('http://localhost:8000/', $location);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->get('/auth/kakao/redirect')->assertNotFound();
    }

    public function test_first_login_creates_pending_user_and_goes_to_signup(): void
    {
        $this->fakeSocialUser('google', 'google-123');

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('signup.show'));

        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame('player@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);
    }

    public function test_returning_user_logs_in_and_goes_home(): void
    {
        $account = SocialAccount::factory()->create(['provider_user_id' => 'google-123']);
        $this->fakeSocialUser('google', 'google-123');

        $this->get(route('social.callback', 'google'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($account->user);
        $this->assertSame(1, User::count());
    }

    public function test_apple_refresh_token_is_stored_encrypted(): void
    {
        $this->fakeSocialUser('apple', 'apple-001', 'abc@privaterelay.appleid.com', 'apple-refresh-token');

        $this->post(route('social.callback', 'apple'))->assertRedirect(route('signup.show'));

        $account = SocialAccount::sole();
        $this->assertSame(LoginProvider::Apple, $account->provider);
        $this->assertSame('apple-refresh-token', $account->refresh_token);
        $this->assertNotSame('apple-refresh-token', DB::table('social_accounts')->value('refresh_token'));
    }

    public function test_apple_login_without_email_keeps_first_email(): void
    {
        $account = SocialAccount::factory()->create([
            'provider' => LoginProvider::Apple,
            'provider_user_id' => 'apple-001',
            'email' => 'first@privaterelay.appleid.com',
        ]);
        $this->fakeSocialUser('apple', 'apple-001', null);

        $this->post(route('social.callback', 'apple'))->assertRedirect(route('home'));

        $this->assertSame('first@privaterelay.appleid.com', $account->fresh()->email);
    }

    public function test_same_email_from_another_provider_is_not_merged(): void
    {
        User::factory()->create(['email' => 'player@example.com']);
        $this->fakeSocialUser('apple', 'apple-777', 'player@example.com');

        $this->post(route('social.callback', 'apple'))->assertRedirect(route('signup.show'));

        $this->assertSame(2, User::count());
        $newUser = User::query()->latest('id')->first();
        $this->assertNull($newUser->email);
        $this->assertSame('player@example.com', $newUser->socialAccounts()->sole()->email);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->suspended()->create();
        SocialAccount::factory()->for($user)->create(['provider_user_id' => 'google-123']);
        $this->fakeSocialUser('google', 'google-123');

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('loginError');

        $this->assertGuest();
    }

    public function test_recently_withdrawn_account_cannot_rejoin(): void
    {
        WithdrawnIdentityHash::create([
            'identity_hash' => WithdrawnIdentityHash::hashFor('google', 'google-123'),
            'expires_at' => now()->addDays(10),
        ]);
        $this->fakeSocialUser('google', 'google-123');

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('loginError', fn (string $message) => str_contains($message, '30일'));

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_withdrawn_account_can_rejoin_after_restriction_expires(): void
    {
        WithdrawnIdentityHash::create([
            'identity_hash' => WithdrawnIdentityHash::hashFor('google', 'google-123'),
            'expires_at' => now()->subDay(),
        ]);
        $this->fakeSocialUser('google', 'google-123');

        $this->get(route('social.callback', 'google'))->assertRedirect(route('signup.show'));
    }

    public function test_provider_error_returns_to_login_with_message(): void
    {
        Socialite::fake('google', fn () => throw new RuntimeException('invalid state'));

        $this->get(route('social.callback', 'google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('loginError');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_password_login_routes_are_disabled(): void
    {
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'secret'])->assertStatus(405);
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }
}
