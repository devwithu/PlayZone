<?php

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\GameReview;
use App\Models\Platform;
use App\Models\User;
use App\Services\GameRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 웹 리뷰 화면 (W-05 리뷰 영역, W-06 상세, W-07 작성). 규칙은 Api\V1\ReviewTest가 확인하고, 여기서는 화면 흐름을 본다.
 */
class ReviewWebTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Platform $platform;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['slug' => '별빛-원정대']);
        $this->platform = Platform::query()->orderBy('id')->firstOrFail();
        GameReleaseDate::factory()->create(['game_id' => $this->game->id, 'platform_id' => $this->platform->id]);
        $this->user = User::factory()->create();
    }

    private function review(array $attributes = []): GameReview
    {
        return GameReview::factory()->create(['game_id' => $this->game->id, 'platform_id' => $this->platform->id, ...$attributes]);
    }

    public function test_game_page_shows_summary_and_sorted_reviews(): void
    {
        $low = $this->review(['overall_score' => 3, 'like_count' => 9]);
        $high = $this->review(['overall_score' => 9]);
        $this->review(['status' => ReviewStatus::Hidden]);
        app(GameRatingService::class)->recalculate($this->game->id);

        $this->get('/games/별빛-원정대')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('games/Show')
            ->where('summary.review_count', 2)
            ->where('summary.distribution.9', 1)
            ->where('reviews.pagination.total', 2)
            ->where('reviews.items.0.id', $low->id)
            ->where('reviews.items.0.is_liked', null)
            ->where('sort', 'likes')
            ->where('game.my_review_id', null));

        $this->get('/games/별빛-원정대?sort=score_high')->assertInertia(fn (Assert $page) => $page
            ->where('reviews.items.0.id', $high->id)
            ->where('sort', 'score_high'));
        $this->get('/games/별빛-원정대?sort=bad')->assertInertia(fn (Assert $page) => $page->where('sort', 'likes'));
    }

    public function test_write_flow(): void
    {
        $this->actingAs($this->user);

        $this->get('/games/별빛-원정대/reviews/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reviews/Form')
            ->where('review', null)
            ->where('platforms.0.id', $this->platform->id)
            ->has('categories', 6));

        $this->post("/games/{$this->game->id}/reviews", ['title' => '짧'])->assertSessionHasErrors(['title', 'content', 'platform_id', 'overall_score']);

        $response = $this->post("/games/{$this->game->id}/reviews", [
            'title' => '오픈월드의 교과서',
            'content' => str_repeat('탐험할수록 새로운 것이 나오는 게임입니다. ', 3),
            'platform_id' => $this->platform->id,
            'overall_score' => 9,
            'ratings' => ['graphics' => 8, 'sound' => null],
        ]);
        $review = GameReview::query()->sole();
        $response->assertRedirect("/reviews/{$review->id}")->assertInertiaFlash('toast.message', '리뷰를 등록했습니다. 100P를 받았습니다.');
        $this->assertSame(['graphics' => 8], $review->load('ratings.category')->ratingMap());

        // 이미 쓴 게임이면 작성 화면 대신 수정 화면으로
        $this->get('/games/별빛-원정대/reviews/create')->assertRedirect("/reviews/{$review->id}/edit");
        $this->get("/reviews/{$review->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reviews/Form')
            ->where('review.id', $review->id)
            ->where('review.ratings.graphics', 8));

        $this->put("/reviews/{$review->id}", [
            'title' => '고친 제목',
            'content' => str_repeat('다시 해 보니 생각이 조금 바뀌었습니다. ', 3),
            'platform_id' => $this->platform->id,
            'overall_score' => 7,
        ])->assertRedirect("/reviews/{$review->id}");
        $this->assertSame('고친 제목', $review->refresh()->title);

        $this->delete("/reviews/{$review->id}")->assertRedirect(route('games.show', '별빛-원정대'));
        $this->assertSame(ReviewStatus::Deleted, $review->refresh()->status);
    }

    public function test_create_page_for_unreleased_game_goes_back_with_message(): void
    {
        $upcoming = Game::factory()->create(['slug' => '푸른-등대']);
        GameReleaseDate::factory()->upcoming()->create(['game_id' => $upcoming->id]);

        $this->actingAs($this->user)
            ->get('/games/푸른-등대/reviews/create')
            ->assertRedirect(route('games.show', '푸른-등대'))
            ->assertInertiaFlash('toast.message', '출시 전 게임에는 리뷰를 쓸 수 없습니다.');

        $this->app['auth']->forgetGuards();
        $this->get('/games/별빛-원정대/reviews/create')->assertRedirect('/login');
    }

    public function test_detail_page_is_public_with_meta(): void
    {
        $review = $this->review(['title' => '최고의 탐험']);

        $this->get("/reviews/{$review->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reviews/Show')
            ->where('review.title', '최고의 탐험')
            ->where('review.game.slug', '별빛-원정대')
            ->where('review.is_mine', null)
            ->where('comments.pagination.total', 0)
            ->where('meta.url', route('reviews.show', $review->id)));

        $review->update(['status' => ReviewStatus::Blinded]);
        $this->get("/reviews/{$review->id}")->assertNotFound();
        $this->actingAs($review->user)->get("/reviews/{$review->id}")->assertInertia(fn (Assert $page) => $page
            ->where('review.status', 'blinded')
            ->where('comments', null));
    }

    public function test_like_and_comment_from_detail_page(): void
    {
        $review = $this->review();
        $this->actingAs($this->user);

        $this->from("/reviews/{$review->id}")->post("/reviews/{$review->id}/like")->assertRedirect("/reviews/{$review->id}");
        $this->assertSame(1, $review->refresh()->like_count);
        $this->from("/reviews/{$review->id}")->delete("/reviews/{$review->id}/like");
        $this->assertSame(0, $review->refresh()->like_count);

        $this->from("/reviews/{$review->id}")
            ->post("/reviews/{$review->id}/comments", ['content' => '공감합니다'])
            ->assertRedirect("/reviews/{$review->id}")
            ->assertInertiaFlash('toast.message', '댓글을 등록했습니다. 5P를 받았습니다.');

        // 본인 리뷰 추천은 오류 화면 대신 토스트
        $this->actingAs($review->user)
            ->from("/reviews/{$review->id}")
            ->post("/reviews/{$review->id}/like")
            ->assertRedirect("/reviews/{$review->id}")
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => '본인 리뷰는 추천할 수 없습니다.']);
    }

    public function test_others_cannot_open_edit_page(): void
    {
        $review = $this->review();

        $this->actingAs($this->user)
            ->get("/reviews/{$review->id}/edit")
            ->assertRedirect("/reviews/{$review->id}")
            ->assertInertiaFlash('toast.message', '본인 리뷰만 수정할 수 있습니다.');
    }
}
