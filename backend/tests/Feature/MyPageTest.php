<?php

namespace Tests\Feature;

use App\Enums\PointType;
use App\Enums\ReviewStatus;
use App\Models\Board;
use App\Models\Game;
use App\Models\GameReview;
use App\Models\Post;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\FavoriteService;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 마이페이지 (REQ-601, 603, 313, 504, 505, 화면 설계 W-17, api-spec §7.7)
 */
class MyPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-21 03:00:00');
        $this->user = User::factory()->create(['nickname' => '게임평가왕']);
    }

    private function seedActivity(): void
    {
        app(AttendanceService::class)->check($this->user);
        app(PointService::class)->earn($this->user, PointType::AdminGrant, 'test:grant', 100, description: '테스트 지급');
        app(FavoriteService::class)->add($this->user, Game::factory()->create());

        GameReview::factory()->for($this->user)->create(['title' => '공개 리뷰', 'created_at' => now()->subDays(2)]);
        GameReview::factory()->for($this->user)->status(ReviewStatus::Hidden)->create(['title' => '숨긴 리뷰', 'hidden_reason' => '욕설']);
        GameReview::factory()->for($this->user)->status(ReviewStatus::Deleted)->create();
        GameReview::factory()->create();

        Post::factory()->for($this->user)->create(['title' => '내 글']);
        Post::factory()->for($this->user)->create(['status' => ReviewStatus::Blinded, 'title' => '검토 중 글']);
        Post::factory()->for($this->user)->create(['status' => ReviewStatus::Deleted]);
        Post::factory()->create();

        // 로그인한 요청은 DB에서 최신 값을 읽으므로 같게 맞춘다
        $this->user->refresh();
    }

    public function test_dashboard_shows_header_and_counts(): void
    {
        $this->seedActivity();

        $this->actingAs($this->user)->get('/mypage')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('mypage/Dashboard')
            ->where('header.nickname', '게임평가왕')
            ->where('header.point_balance', 110)
            ->where('header.lifetime_points', 110)
            ->has('header.level.name')
            ->where('attendance', ['attended_today' => true, 'streak_days' => 1, 'month_count' => 1])
            ->where('counts', ['reviews' => 2, 'posts' => 2, 'favorites' => 1])
            ->has('recentPoints', 2)
            ->where('recentPoints.0.type', 'admin_grant'));
    }

    public function test_my_reviews_and_posts_include_hidden_with_reason(): void
    {
        $this->seedActivity();
        $this->actingAs($this->user);

        $this->get('/mypage/reviews')->assertInertia(fn (Assert $page) => $page
            ->component('mypage/Reviews')
            ->where('reviews.pagination.total', 2)
            ->where('reviews.items.0.title', '숨긴 리뷰')
            ->where('reviews.items.0.status', 'hidden')
            ->where('reviews.items.0.hidden_reason', '욕설')
            ->has('reviews.items.0.game.slug')
            ->where('reviews.items.1.status', 'published'));

        Board::query()->where('slug', 'free')->update(['is_active' => false]);
        $this->get('/mypage/posts')->assertInertia(fn (Assert $page) => $page
            ->component('mypage/Posts')
            ->where('posts.pagination.total', 2)
            ->where('posts.items.0.title', '검토 중 글')
            ->where('posts.items.0.status', 'blinded')
            ->where('posts.items.0.board.is_active', false));
    }

    public function test_points_tab_filters_by_type(): void
    {
        $this->seedActivity();
        $this->actingAs($this->user);

        $this->get('/mypage/points')->assertInertia(fn (Assert $page) => $page
            ->component('mypage/Points')
            ->where('transactions.pagination.total', 2)
            ->where('type', null)
            ->has('types', count(PointType::cases())));
        $this->get('/mypage/points?type=attendance')->assertInertia(fn (Assert $page) => $page
            ->where('type', 'attendance')
            ->where('transactions.pagination.total', 1)
            ->where('transactions.items.0.amount', 10)
            ->where('transactions.items.0.balance_after', 10));
        // 잘못된 사유는 전체로 보여 준다
        $this->get('/mypage/points?type=bad')->assertInertia(fn (Assert $page) => $page
            ->where('type', null)
            ->where('transactions.pagination.total', 2));
    }

    public function test_tabs_need_completed_signup(): void
    {
        foreach (['/mypage', '/mypage/reviews', '/mypage/posts', '/mypage/points', '/mypage/attendance', '/mypage/favorites'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->actingAs(User::factory()->pending()->create())->get('/mypage')->assertRedirect(route('signup.show'));
    }

    public function test_api_dashboard_and_reviews(): void
    {
        $this->seedActivity();
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/mypage')->assertOk()
            ->assertJsonPath('data.point_balance', 110)
            ->assertJsonPath('data.counts.reviews', 2)
            ->assertJsonPath('data.attendance.month_count', 1)
            ->assertJsonStructure(['data' => ['level' => ['name'], 'next_level']]);

        $this->getJson('/api/v1/mypage/reviews?per_page=1')->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.items.0.status', 'hidden')
            ->assertJsonPath('data.items.0.hidden_reason', '욕설')
            ->assertJsonPath('data.items.0.is_mine', true);

        Sanctum::actingAs(User::factory()->pending()->create());
        $this->getJson('/api/v1/mypage')->assertForbidden();
    }
}
