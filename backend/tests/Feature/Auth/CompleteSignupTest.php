<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompleteSignupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'nickname' => '게임평가왕',
            'age_confirmed' => true,
            'terms' => true,
            'privacy' => true,
            'marketing' => false,
        ], $overrides);
    }

    public function test_pending_user_sees_signup_page(): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->get(route('signup.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/CompleteSignup'));
    }

    public function test_active_user_is_redirected_away_from_signup_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('signup.show'))
            ->assertRedirect(route('home'));
    }

    public function test_pending_user_is_sent_to_signup_from_member_pages(): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->get(route('dashboard'))
            ->assertRedirect(route('signup.show'));
    }

    public function test_pending_user_can_complete_signup(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user)
            ->post(route('signup.store'), $this->validInput(['marketing' => true]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('게임평가왕', $user->nickname);
        $this->assertNotNull($user->signup_completed_at);
        $this->assertNotNull($user->age_confirmed_at);
        $this->assertNotNull($user->terms_agreed_at);
        $this->assertNotNull($user->marketing_agreed_at);
        $this->assertTrue($user->hasRole('member'));
    }

    public function test_marketing_consent_is_optional(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user)
            ->post(route('signup.store'), $this->validInput(['marketing' => false]))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->marketing_agreed_at);
    }

    public function test_after_signup_user_returns_to_intended_page(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user)->get(route('dashboard'));

        $this->post(route('signup.store'), $this->validInput())
            ->assertRedirect(route('dashboard'));
    }

    public function test_under_14_cannot_sign_up(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user)
            ->post(route('signup.store'), $this->validInput(['age_confirmed' => false]))
            ->assertSessionHasErrors(['age_confirmed' => '만 14세 미만은 가입할 수 없습니다.']);

        $this->assertTrue($user->fresh()->isPending());
    }

    public function test_required_agreements_are_checked(): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->post(route('signup.store'), $this->validInput(['terms' => false, 'privacy' => false]))
            ->assertSessionHasErrors(['terms', 'privacy']);
    }

    public function test_nickname_is_unique_ignoring_case(): void
    {
        User::factory()->create(['nickname' => 'Player']);

        $this->actingAs(User::factory()->pending()->create())
            ->post(route('signup.store'), $this->validInput(['nickname' => 'player']))
            ->assertSessionHasErrors(['nickname' => '이미 사용 중인 닉네임입니다.']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNicknames(): array
    {
        return [
            '1자' => ['가'],
            '13자' => ['가나다라마바사아자차카타파'],
            '공백' => ['게임 평가'],
            '특수문자' => ['game!'],
        ];
    }

    #[DataProvider('invalidNicknames')]
    public function test_nickname_format_is_validated(string $nickname): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->post(route('signup.store'), $this->validInput(['nickname' => $nickname]))
            ->assertSessionHasErrors('nickname');
    }

    public function test_twelve_character_nickname_is_allowed(): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->post(route('signup.store'), $this->validInput(['nickname' => '가나다라마바사아자차카타']))
            ->assertSessionHasNoErrors();
    }

    public function test_nickname_check_reports_availability(): void
    {
        User::factory()->create(['nickname' => 'taken']);
        $this->actingAs(User::factory()->pending()->create());

        $this->getJson(route('signup.nickname-check', ['nickname' => 'TAKEN']))
            ->assertOk()
            ->assertJson(['available' => false, 'message' => '이미 사용 중인 닉네임입니다.']);

        $this->getJson(route('signup.nickname-check', ['nickname' => 'free_name']))
            ->assertOk()
            ->assertJson(['available' => true]);
    }
}
