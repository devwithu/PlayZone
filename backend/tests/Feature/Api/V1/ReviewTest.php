<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\GameReview;
use App\Models\Platform;
use App\Models\PointTransaction;
use App\Models\RatingCategory;
use App\Models\ReviewRevision;
use App\Models\User;
use App\Services\GameRatingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 리뷰 작성·수정·삭제·조회와 평균 집계 (api-spec §7.3, 리뷰 정책)
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private Platform $released;

    private Platform $upcoming;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        [$this->released, $this->upcoming] = Platform::query()->orderBy('id')->take(2)->get()->all();
        GameReleaseDate::factory()->create(['game_id' => $this->game->id, 'platform_id' => $this->released->id]);
        GameReleaseDate::factory()->upcoming()->create(['game_id' => $this->game->id, 'platform_id' => $this->upcoming->id]);
        $this->user = User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return [
            'title' => '오픈월드의 교과서',
            'content' => str_repeat('탐험할수록 새로운 것이 나오는 게임입니다. ', 3),
            'platform_id' => $this->released->id,
            'overall_score' => 9,
            'ratings' => ['graphics' => 9, 'story' => 10],
            'has_spoiler' => false,
            ...$override,
        ];
    }

    private function write(?User $user = null, array $override = []): GameReview
    {
        Sanctum::actingAs($user ?? $this->user);
        $id = $this->postJson("/api/v1/games/{$this->game->id}/reviews", $this->payload($override))
            ->assertCreated()
            ->json('data.review.id');

        return GameReview::query()->findOrFail($id);
    }

    public function test_write_review_grants_points_once_per_game(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->postJson("/api/v1/games/{$this->game->id}/reviews", $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', '리뷰를 등록했습니다. 100P를 받았습니다.')
            ->assertJsonPath('data.point', ['earned' => 100, 'balance' => 100, 'type' => 'review_create', 'limited' => false])
            ->assertJsonPath('data.review.title', '오픈월드의 교과서')
            ->assertJsonPath('data.review.ratings', ['graphics' => 9, 'story' => 10])
            ->assertJsonPath('data.review.author.nickname', $this->user->nickname)
            ->assertJsonPath('data.review.platform.id', $this->released->id)
            ->assertJsonPath('data.review.is_mine', true)
            ->assertJsonPath('data.review.status', 'published');
        $reviewId = $response->json('data.review.id');

        // 두 번째는 막고 기존 리뷰 ID를 알려 준다
        $this->postJson("/api/v1/games/{$this->game->id}/reviews", $this->payload())
            ->assertStatus(409)
            ->assertJsonPath('code', 'REVIEW_ALREADY_EXISTS')
            ->assertJsonPath('errors.review_id', $reviewId);

        // 지우고 다시 쓰면 되지만 포인트는 다시 주지 않는다
        $this->deleteJson("/api/v1/reviews/{$reviewId}")->assertOk();
        $this->postJson("/api/v1/games/{$this->game->id}/reviews", $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', '리뷰를 등록했습니다.')
            ->assertJsonPath('data.point.earned', 0);

        $this->assertSame(100, $this->user->refresh()->point_balance);
        $this->assertSame(1, PointTransaction::query()->where('idempotency_key', "review_create:{$this->user->id}:{$this->game->id}")->count());
    }

    public function test_db_allows_only_one_live_review_per_game(): void
    {
        $attributes = ['game_id' => $this->game->id, 'user_id' => $this->user->id, 'platform_id' => $this->released->id];
        GameReview::factory()->status(ReviewStatus::Deleted)->create($attributes);
        GameReview::factory()->create($attributes);

        $this->expectException(UniqueConstraintViolationException::class);
        GameReview::factory()->create($attributes);
    }

    public function test_write_preconditions_in_spec_order(): void
    {
        Sanctum::actingAs($this->user);
        $upcomingGame = Game::factory()->create();
        GameReleaseDate::factory()->upcoming()->create(['game_id' => $upcomingGame->id]);

        // 출시 전 게임은 입력값이 틀려도 GAME_NOT_RELEASED가 먼저
        $this->postJson("/api/v1/games/{$upcomingGame->id}/reviews", [])->assertStatus(409)->assertJsonPath('code', 'GAME_NOT_RELEASED');

        GameReview::factory()->status(ReviewStatus::Hidden)->create(['game_id' => $this->game->id, 'user_id' => $this->user->id, 'platform_id' => $this->released->id]);
        $this->postJson("/api/v1/games/{$this->game->id}/reviews", [])->assertStatus(409)->assertJsonPath('code', 'REVIEW_HIDDEN_EXISTS');

        $hiddenGame = Game::factory()->hidden()->create();
        $this->postJson("/api/v1/games/{$hiddenGame->id}/reviews", $this->payload())->assertNotFound();
    }

    public function test_validation_rules(): void
    {
        RatingCategory::query()->where('slug', 'value')->update(['is_active' => false]);
        Sanctum::actingAs($this->user);
        $url = "/api/v1/games/{$this->game->id}/reviews";

        $this->postJson($url, $this->payload([
            'title' => 'a',
            'content' => '짧은 본문',
            'platform_id' => $this->upcoming->id,
            'overall_score' => 11,
            'ratings' => ['graphics' => 0],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['title', 'content', 'platform_id', 'overall_score', 'ratings.graphics']);

        // 비활성 항목·없는 항목은 받지 않는다
        $this->postJson($url, $this->payload(['ratings' => ['value' => 5]]))->assertUnprocessable()->assertJsonValidationErrors('ratings');
        $this->postJson($url, $this->payload(['ratings' => ['nope' => 5]]))->assertUnprocessable()->assertJsonValidationErrors('ratings');

        // 세부 점수는 선택. null은 입력하지 않은 것
        $this->postJson($url, $this->payload(['ratings' => ['graphics' => null, 'sound' => 7]]))
            ->assertCreated()
            ->assertJsonPath('data.review.ratings', ['sound' => 7]);
    }

    public function test_pending_or_guest_cannot_write(): void
    {
        $this->postJson("/api/v1/games/{$this->game->id}/reviews", $this->payload())->assertUnauthorized();

        Sanctum::actingAs(User::factory()->pending()->create());
        $this->postJson("/api/v1/games/{$this->game->id}/reviews", $this->payload())->assertForbidden();
    }

    public function test_update_keeps_revision_and_recalculates(): void
    {
        $review = $this->write();
        $this->putJson("/api/v1/reviews/{$review->id}", $this->payload([
            'title' => '다시 해 보니 아쉬운 점도',
            'overall_score' => 6,
            'ratings' => ['sound' => 5],
            'has_spoiler' => true,
        ]))
            ->assertOk()
            ->assertJsonPath('data.title', '다시 해 보니 아쉬운 점도')
            ->assertJsonPath('data.ratings', ['sound' => 5])
            ->assertJsonPath('data.has_spoiler', true)
            ->assertJsonPath('data.edited_at', fn ($v) => is_string($v));

        $revision = ReviewRevision::query()->sole();
        // jsonb는 키 순서를 보존하지 않는다
        $this->assertEquals(['오픈월드의 교과서', 9, ['graphics' => 9, 'story' => 10]], [$revision->title, $revision->overall_score, $revision->ratings]);
        $this->assertSame(100, $this->user->refresh()->point_balance, '수정은 포인트가 없다');
    }

    public function test_only_author_edits_and_only_published(): void
    {
        $review = $this->write();

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/reviews/{$review->id}", $this->payload())->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->deleteJson("/api/v1/reviews/{$review->id}")->assertForbidden();

        $review->update(['status' => ReviewStatus::Blinded]);
        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/reviews/{$review->id}", $this->payload())->assertStatus(409)->assertJsonPath('code', 'NOT_EDITABLE');
        $this->deleteJson("/api/v1/reviews/{$review->id}")->assertStatus(409)->assertJsonPath('code', 'NOT_EDITABLE');

        $this->putJson('/api/v1/reviews/999999', $this->payload())->assertNotFound();
    }

    public function test_visibility_of_non_public_reviews(): void
    {
        $review = $this->write();
        $review->update(['status' => ReviewStatus::Hidden, 'hidden_reason' => '욕설']);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/reviews/{$review->id}")->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'hidden')
            ->assertJsonPath('data.hidden_reason', '욕설');

        $review->update(['status' => ReviewStatus::Deleted]);
        $this->getJson("/api/v1/reviews/{$review->id}")->assertNotFound();
    }

    public function test_public_detail_hides_status_and_shows_withdrawn_author(): void
    {
        $review = $this->write();
        $this->user->forceFill(['status' => UserStatus::Withdrawn, 'nickname' => 'withdrawn_'.$this->user->id])->save();

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/reviews/{$review->id}")
            ->assertOk()
            ->assertJsonPath('data.author', ['id' => null, 'nickname' => '탈퇴한 회원', 'level' => null])
            ->assertJsonPath('data.game.slug', $this->game->slug)
            ->assertJsonPath('data.is_mine', null)
            ->assertJsonMissingPath('data.status');
    }

    public function test_list_sorts_and_viewer_flags(): void
    {
        $a = GameReview::factory()->create(['game_id' => $this->game->id, 'platform_id' => $this->released->id, 'overall_score' => 5, 'like_count' => 3]);
        $b = GameReview::factory()->create(['game_id' => $this->game->id, 'platform_id' => $this->released->id, 'overall_score' => 9, 'like_count' => 1]);
        $c = GameReview::factory()->create(['game_id' => $this->game->id, 'platform_id' => $this->released->id, 'overall_score' => 2, 'like_count' => 7]);
        GameReview::factory()->status(ReviewStatus::Hidden)->create(['game_id' => $this->game->id, 'platform_id' => $this->released->id]);
        $url = "/api/v1/games/{$this->game->id}/reviews";

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.items.*.id', [$c->id, $a->id, $b->id])
            ->assertJsonPath('data.items.0.is_liked', null);
        $this->getJson("{$url}?sort=latest")->assertJsonPath('data.items.*.id', [$c->id, $b->id, $a->id]);
        $this->getJson("{$url}?sort=score_high")->assertJsonPath('data.items.*.id', [$b->id, $a->id, $c->id]);
        $this->getJson("{$url}?sort=score_low")->assertJsonPath('data.items.*.id', [$c->id, $a->id, $b->id]);
        $this->getJson("{$url}?sort=bad")->assertUnprocessable();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/reviews/{$b->id}/like")->assertOk();
        $this->getJson("{$url}?sort=score_high")
            ->assertJsonPath('data.items.0.is_liked', true)
            ->assertJsonPath('data.items.1.is_liked', false)
            ->assertJsonPath('data.items.0.is_mine', false);
    }

    public function test_average_needs_three_public_reviews_and_follows_status(): void
    {
        $this->write(null, ['overall_score' => 8, 'ratings' => ['graphics' => 6]]);
        $this->write(User::factory()->create(), ['overall_score' => 9, 'ratings' => ['graphics' => 9, 'sound' => 7]]);

        $summary = "/api/v1/games/{$this->game->id}/rating-summary";
        $this->getJson($summary)
            ->assertJsonPath('data.review_count', 2)
            ->assertJsonPath('data.rating_avg', null)
            ->assertJsonPath('data.distribution.8', 1)
            ->assertJsonPath('data.categories.0', ['slug' => 'graphics', 'name' => '그래픽', 'avg_score' => null, 'score_count' => 2]);

        $third = $this->write(User::factory()->create(), ['overall_score' => 10, 'ratings' => []]);
        $this->assertEquals(9.0, $this->game->refresh()->rating_avg);
        $this->getJson($summary)
            ->assertJsonPath('data.rating_avg', 9)
            ->assertJsonPath('data.categories.0.avg_score', 7.5)
            ->assertJsonPath('data.categories.1.avg_score', 7)
            ->assertJsonPath('data.categories.2.avg_score', null);
        $this->getJson("/api/v1/games/{$this->game->id}")->assertJsonPath('data.review_count', 3)->assertJsonPath('data.rating_avg', 9);

        // 작성자가 지우면 평균에서 빠진다
        Sanctum::actingAs($third->user);
        $this->deleteJson("/api/v1/reviews/{$third->id}")->assertOk();
        $this->game->refresh();
        $this->assertSame([2, null], [$this->game->review_count, $this->game->rating_avg]);

        // 관리자 숨김 등 서비스 밖에서 바뀌어도 다시 계산하면 맞는다
        GameReview::query()->update(['status' => ReviewStatus::Hidden]);
        app(GameRatingService::class)->recalculate($this->game->id);
        $this->assertSame(0, $this->game->refresh()->review_count);
        $this->getJson($summary)->assertJsonPath('data.categories.0.score_count', 0);
    }

    public function test_game_detail_has_my_review_id_and_categories_endpoint(): void
    {
        $review = $this->write();

        $this->getJson("/api/v1/games/{$this->game->id}")->assertJsonPath('data.my_review_id', $review->id);
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/games/{$this->game->id}")->assertJsonPath('data.my_review_id', null);

        $this->getJson('/api/v1/rating-categories')
            ->assertOk()
            ->assertJsonPath('data.*.slug', ['graphics', 'sound', 'story', 'controls', 'gameplay', 'value']);
    }
}
