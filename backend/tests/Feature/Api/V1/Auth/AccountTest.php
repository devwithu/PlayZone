<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\LoginProvider;
use App\Enums\UserStatus;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 앱 인증 API: 내 정보, 가입 완료, 닉네임 확인, 로그아웃 (api-spec §7.1)
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function signupBody(array $overrides = []): array
    {
        return [
            'nickname' => '젤다러버',
            'age_confirmed' => true,
            'terms' => true,
            'privacy' => true,
            'marketing' => false,
            ...$overrides,
        ];
    }

    public function test_endpoints_require_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->postJson('/api/v1/auth/signup/complete', $this->signupBody())->assertUnauthorized();
        $this->getJson('/api/v1/auth/nickname-check?nickname=abc')->assertUnauthorized();
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_me_returns_my_info_and_social_accounts(): void
    {
        $user = User::factory()->create(['nickname' => '젤다러버', 'point_balance' => 1250, 'lifetime_points' => 3000]);
        SocialAccount::factory()->for($user)->create(['provider' => LoginProvider::Google, 'created_at' => now()->subDay()]);
        SocialAccount::factory()->for($user)->create(['provider' => LoginProvider::Apple]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.nickname', '젤다러버')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.signup_completed', true)
            ->assertJsonPath('data.point_balance', 1250)
            ->assertJsonPath('data.lifetime_points', 3000)
            ->assertJsonPath('data.social_accounts.0.provider', 'google')
            ->assertJsonPath('data.social_accounts.1.provider', 'apple');

        $this->assertStringNotContainsString('email', $response->getContent());
    }

    public function test_pending_user_can_call_me(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.signup_completed', false)
            ->assertJsonPath('data.nickname', null);
    }

    public function test_pending_user_completes_signup(): void
    {
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/signup/complete', $this->signupBody(['marketing' => true]))
            ->assertOk()
            ->assertJsonPath('message', '가입이 완료되었습니다.')
            ->assertJsonPath('data.signup_completed', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.nickname', '젤다러버')
            ->assertJsonPath('data.marketing_agreed', true);

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->age_confirmed_at);
        $this->assertNotNull($user->terms_agreed_at);
        $this->assertTrue($user->roles()->where('slug', 'member')->exists());
    }

    public function test_signup_requires_age_terms_privacy_and_valid_nickname(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->postJson('/api/v1/auth/signup/complete', [
            'nickname' => 'a',
            'age_confirmed' => false,
            'terms' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.age_confirmed.0', '만 14세 미만은 가입할 수 없습니다.')
            ->assertJsonStructure(['errors' => ['nickname', 'age_confirmed', 'terms', 'privacy']]);
    }

    public function test_signup_rejects_taken_nickname_case_insensitively(): void
    {
        User::factory()->create(['nickname' => 'Player_1']);
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->postJson('/api/v1/auth/signup/complete', $this->signupBody(['nickname' => 'player_1']))
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['nickname']]);
    }

    public function test_completing_signup_again_changes_nothing(): void
    {
        $user = User::factory()->create(['nickname' => '원래닉네임']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/signup/complete', $this->signupBody(['nickname' => '새닉네임']))
            ->assertOk()
            ->assertJsonPath('data.nickname', '원래닉네임');
    }

    public function test_signup_complete_is_rate_limited_to_5_per_minute(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/signup/complete', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/signup/complete', [])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'RATE_LIMITED');
    }

    public function test_nickname_check(): void
    {
        User::factory()->create(['nickname' => '이미있음']);
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->getJson('/api/v1/auth/nickname-check?nickname='.urlencode('새닉네임'))
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.message', '사용할 수 있는 닉네임입니다.');

        $this->getJson('/api/v1/auth/nickname-check?nickname='.urlencode('이미있음'))
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->getJson('/api/v1/auth/nickname-check?nickname='.urlencode('공백 포함'))
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->getJson('/api/v1/auth/nickname-check')
            ->assertOk()
            ->assertJsonPath('data.available', false);
    }

    public function test_own_nickname_is_available_to_self(): void
    {
        Sanctum::actingAs(User::factory()->create(['nickname' => '내닉네임']));

        $this->getJson('/api/v1/auth/nickname-check?nickname='.urlencode('내닉네임'))
            ->assertOk()
            ->assertJsonPath('data.available', true);
    }

    public function test_logout_revokes_only_current_device_token(): void
    {
        $user = User::factory()->create();
        $android = $user->createToken('Unity Android')->plainTextToken;
        $ios = $user->createToken('Unity iOS')->plainTextToken;

        $this->withToken($android)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', '로그아웃되었습니다.');

        $this->assertSame(['Unity iOS'], $user->tokens()->pluck('name')->all());

        // 같은 테스트 안에서 이전 요청의 로그인 상태가 남지 않게 한다
        $this->app['auth']->forgetGuards();

        $this->withToken($android)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($ios)->getJson('/api/v1/auth/me')->assertOk();
    }
}
