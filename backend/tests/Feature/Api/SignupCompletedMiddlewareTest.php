<?php

namespace Tests\Feature\Api;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 인증 O 엔드포인트(auth:sanctum + signup.completed)의 회원 상태 확인 (api-spec §2).
 */
class SignupCompletedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'signup.completed'])
            ->get('api/v1/__test/members-only', fn () => ApiResponse::success(['ok' => true]));
    }

    public function test_active_member_passes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/__test/members-only')
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    public function test_pending_member_gets_signup_incomplete(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());

        $this->getJson('/api/v1/__test/members-only')
            ->assertForbidden()
            ->assertJsonPath('code', 'SIGNUP_INCOMPLETE');
    }

    public function test_suspended_member_gets_account_suspended(): void
    {
        Sanctum::actingAs(User::factory()->suspended()->create());

        $this->getJson('/api/v1/__test/members-only')
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_SUSPENDED');
    }
}
