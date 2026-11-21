<?php

namespace Tests\Feature\Api\V1;

use App\Enums\PointType;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 포인트 API (api-spec §3 data.point, §7.6)
 */
class PointTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_completion_grants_points_once(): void
    {
        $user = User::factory()->pending()->create();
        Sanctum::actingAs($user);
        $body = ['nickname' => '포인트왕', 'age_confirmed' => true, 'terms' => true, 'privacy' => true];

        $this->postJson('/api/v1/auth/signup/complete', $body)
            ->assertOk()
            ->assertJsonPath('data.point', ['earned' => 100, 'balance' => 100, 'type' => 'signup', 'limited' => false])
            ->assertJsonPath('data.point_balance', 100)
            ->assertJsonPath('data.lifetime_points', 100);

        $this->postJson('/api/v1/auth/signup/complete', $body)
            ->assertOk()
            ->assertJsonPath('data.point.earned', 0)
            ->assertJsonPath('data.point_balance', 100);

        $this->assertDatabaseCount('point_transactions', 1);
    }

    public function test_balance_and_history_with_type_filter(): void
    {
        $user = User::factory()->create();
        $points = app(PointService::class);
        $points->earn($user, PointType::Signup, "signup:{$user->id}");
        $points->earn($user, PointType::Attendance, "attendance:{$user->id}:2026-12-01");
        $points->spend($user, PointType::EventEntry, 30, "event_entry:1:{$user->id}");
        Sanctum::actingAs($user->refresh());

        $this->getJson('/api/v1/points/balance')
            ->assertOk()
            ->assertJsonPath('data.point_balance', 80)
            ->assertJsonPath('data.lifetime_points', 110)
            ->assertJsonPath('data.level.name', '브론즈')
            ->assertJsonPath('data.next_level.points_needed', 890);

        $this->getJson('/api/v1/points/history')
            ->assertOk()
            ->assertJsonPath('data.items.*.type', ['event_entry', 'attendance', 'signup'])
            ->assertJsonPath('data.items.0.amount', -30)
            ->assertJsonPath('data.items.0.balance_after', 80)
            ->assertJsonPath('data.items.0.type_label', '이벤트 응모')
            ->assertJsonPath('data.pagination.total', 3);

        $this->getJson('/api/v1/points/history?type=attendance')
            ->assertJsonPath('data.items.*.type', ['attendance']);

        $this->getJson('/api/v1/points/history?type=bonus')
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_history_shows_only_my_transactions_and_needs_completed_signup(): void
    {
        $other = User::factory()->create();
        app(PointService::class)->earn($other, PointType::Signup, "signup:{$other->id}");

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/points/history')->assertJsonPath('data.pagination.total', 0);

        Sanctum::actingAs(User::factory()->pending()->create());
        $this->getJson('/api/v1/points/balance')->assertForbidden()->assertJsonPath('code', 'SIGNUP_INCOMPLETE');
    }

    public function test_web_signup_shows_point_toast(): void
    {
        $this->actingAs(User::factory()->pending()->create())
            ->post('/signup/complete', ['nickname' => '웹가입', 'age_confirmed' => true, 'terms' => true, 'privacy' => true])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', '가입을 환영합니다! 100P를 받았습니다.');
    }
}
