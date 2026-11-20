<?php

namespace Tests\Feature\Api;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Exceptions\LoginDeniedException;
use App\Http\Requests\Api\V1\PaginatedRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * API 공통 응답·오류 형식 (api-spec §3~§5).
 * 실제 엔드포인트가 생기기 전이라, 테스트 전용 라우트를 /api/v1/__test 아래에 등록해 확인한다.
 */
class ApiResponseFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/__test')->group(function () {
            Route::get('success', fn () => ApiResponse::success(['name' => '젤다'], '불러왔습니다.'));
            Route::get('empty', fn () => ApiResponse::success());
            Route::post('created', fn () => ApiResponse::created(new JsonResource(['id' => 1])));
            Route::get('users', fn (PaginatedRequest $request) => ApiResponse::paginated(
                User::query()->orderBy('id')->paginate($request->perPage()),
                TestUserResource::class,
            ));
            Route::get('users/{user}', fn (User $user) => ApiResponse::success(new TestUserResource($user)));
            Route::post('validate', function (Request $request) {
                $request->validate(['nickname' => ['required']]);

                return ApiResponse::success();
            });
            Route::get('business', fn () => throw ApiException::of(
                ApiErrorCode::ReviewAlreadyExists,
                errors: ['review_id' => 15],
            ));
            Route::get('login-denied', fn () => throw LoginDeniedException::suspended());
            Route::get('forbidden', fn () => abort(403, 'This action is unauthorized.'));
            Route::get('crash', fn () => throw new RuntimeException('DB password is secret'));
            Route::get('me', fn (Request $request) => ApiResponse::success(['id' => $request->user()?->id]))
                ->middleware('auth:sanctum');
            Route::get('throttled', fn () => ApiResponse::success())->middleware('throttle:1,1');
        });
    }

    public function test_success_response_has_envelope(): void
    {
        $this->getJson('/api/v1/__test/success')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => '불러왔습니다.',
                'data' => ['name' => '젤다'],
            ]);
    }

    public function test_success_without_data_returns_empty_object(): void
    {
        $response = $this->getJson('/api/v1/__test/empty')->assertOk();

        $this->assertStringContainsString('"data":{}', $response->getContent());
    }

    public function test_created_response_uses_201_and_unwrapped_resource(): void
    {
        $this->postJson('/api/v1/__test/created')
            ->assertCreated()
            ->assertExactJson(['success' => true, 'message' => '', 'data' => ['id' => 1]]);
    }

    public function test_paginated_response_has_items_and_pagination(): void
    {
        User::factory()->count(3)->create();

        $this->getJson('/api/v1/__test/users?per_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonStructure(['data' => ['items' => [['id', 'nickname']]]])
            ->assertJsonPath('data.pagination', [
                'current_page' => 2,
                'last_page' => 2,
                'per_page' => 2,
                'total' => 3,
            ]);
    }

    public function test_per_page_defaults_to_20(): void
    {
        $this->getJson('/api/v1/__test/users')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 20);
    }

    public function test_per_page_over_50_is_validation_error(): void
    {
        $this->getJson('/api/v1/__test/users?per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['errors' => ['per_page']]);
    }

    public function test_validation_error_format(): void
    {
        $this->postJson('/api/v1/__test/validate')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('message', '입력값을 확인해 주세요.')
            ->assertJsonStructure(['errors' => ['nickname']]);
    }

    public function test_api_exception_uses_its_code_status_and_errors(): void
    {
        $this->getJson('/api/v1/__test/business')
            ->assertConflict()
            ->assertExactJson([
                'success' => false,
                'code' => 'REVIEW_ALREADY_EXISTS',
                'message' => '이 게임에는 이미 리뷰를 작성했습니다.',
                'errors' => ['review_id' => 15],
            ]);
    }

    public function test_error_without_details_returns_empty_errors_object(): void
    {
        $response = $this->getJson('/api/v1/__test/login-denied')
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_SUSPENDED')
            ->assertJsonPath('message', '이용이 정지된 계정입니다. 자세한 내용은 고객센터로 문의해 주세요.');

        $this->assertStringContainsString('"errors":{}', $response->getContent());
    }

    public function test_unauthenticated_request(): void
    {
        $this->getJson('/api/v1/__test/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_request_without_accept_header_still_gets_json(): void
    {
        $this->get('/api/v1/__test/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_bearer_token_authenticates(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Unity Android')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/__test/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_token_expires_after_60_days(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Unity Android');
        $token->accessToken->forceFill(['created_at' => now()->subDays(60)->subMinute()])->save();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/__test/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_framework_messages_are_replaced_with_korean_defaults(): void
    {
        $this->getJson('/api/v1/__test/forbidden')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN')
            ->assertJsonPath('message', '권한이 없습니다.');
    }

    public function test_unknown_route_and_missing_model_are_not_found(): void
    {
        $this->getJson('/api/v1/__test/nothing-here')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->getJson('/api/v1/__test/users/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertJsonPath('message', '요청한 정보를 찾을 수 없습니다.');
    }

    public function test_wrong_method_is_method_not_allowed(): void
    {
        $this->deleteJson('/api/v1/__test/success')
            ->assertMethodNotAllowed()
            ->assertJsonPath('code', 'METHOD_NOT_ALLOWED')
            ->assertHeader('Allow');
    }

    public function test_rate_limited_request_keeps_retry_after_header(): void
    {
        $this->getJson('/api/v1/__test/throttled')->assertOk();

        $this->getJson('/api/v1/__test/throttled')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertHeader('Retry-After');
    }

    public function test_server_error_hides_details(): void
    {
        $response = $this->getJson('/api/v1/__test/crash')
            ->assertInternalServerError()
            ->assertJsonPath('code', 'SERVER_ERROR');

        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_web_routes_keep_default_error_handling(): void
    {
        $response = $this->get('/no-such-web-page')->assertNotFound();

        $this->assertStringNotContainsString('NOT_FOUND', $response->getContent());
    }
}

/**
 * @mixin User
 */
class TestUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'nickname' => $this->nickname];
    }
}
