<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CommentStatus;
use App\Enums\ReviewStatus;
use App\Models\GameReview;
use App\Models\PointPolicy;
use App\Models\PointTransaction;
use App\Models\ReviewComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 리뷰 추천·댓글 (리뷰 정책 §10·§11, 포인트 정책 §3·§5)
 */
class ReviewInteractionTest extends TestCase
{
    use RefreshDatabase;

    private GameReview $review;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->review = GameReview::factory()->create();
        $this->author = $this->review->user;
    }

    public function test_like_is_idempotent_and_rewards_author_once_per_liker(): void
    {
        $liker = User::factory()->create();
        Sanctum::actingAs($liker);
        $url = "/api/v1/reviews/{$this->review->id}/like";

        $this->postJson($url)->assertOk()->assertJsonPath('data', ['liked' => true, 'like_count' => 1]);
        $this->postJson($url)->assertOk()->assertJsonPath('data', ['liked' => true, 'like_count' => 1]);
        $this->deleteJson($url)->assertOk()->assertJsonPath('data', ['liked' => false, 'like_count' => 0]);
        $this->deleteJson($url)->assertOk()->assertJsonPath('data.like_count', 0);
        $this->postJson($url)->assertJsonPath('data.like_count', 1);

        $this->assertSame(5, $this->author->refresh()->point_balance, '취소 후 다시 추천해도 한 번만');
        $this->assertSame(0, $liker->refresh()->point_balance);
        $this->assertSame(
            "review_like_received:{$this->review->id}:{$liker->id}",
            PointTransaction::query()->where('user_id', $this->author->id)->sole()->idempotency_key,
        );
    }

    public function test_like_received_points_stop_at_daily_limit(): void
    {
        PointPolicy::query()->where('action', 'review_like_received')->update(['daily_limit_points' => 10]);

        foreach (range(1, 3) as $_) {
            Sanctum::actingAs(User::factory()->create());
            $this->postJson("/api/v1/reviews/{$this->review->id}/like")->assertOk();
        }

        $this->assertSame(3, $this->review->refresh()->like_count, '추천은 되고 포인트만 한도에서 멈춘다');
        $this->assertSame(10, $this->author->refresh()->point_balance);
    }

    public function test_cannot_like_own_or_non_public_review(): void
    {
        Sanctum::actingAs($this->author);
        $this->postJson("/api/v1/reviews/{$this->review->id}/like")->assertStatus(409)->assertJsonPath('code', 'CANNOT_TARGET_OWN');

        Sanctum::actingAs(User::factory()->create());
        $this->review->update(['status' => ReviewStatus::Blinded]);
        $this->postJson("/api/v1/reviews/{$this->review->id}/like")->assertNotFound();
        $this->postJson('/api/v1/reviews/999999/like')->assertNotFound();
    }

    public function test_comments_write_edit_delete(): void
    {
        $commenter = User::factory()->create();
        Sanctum::actingAs($commenter);
        $base = "/api/v1/reviews/{$this->review->id}/comments";

        $id = $this->postJson($base, ['content' => '  저도 같은 생각입니다  '])
            ->assertCreated()
            ->assertJsonPath('data.comment.content', '저도 같은 생각입니다')
            ->assertJsonPath('data.comment.is_mine', true)
            ->assertJsonPath('data.point', ['earned' => 5, 'balance' => 5, 'type' => 'comment_create', 'limited' => false])
            ->json('data.comment.id');
        $this->postJson($base, ['content' => str_repeat('가', 501)])->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->postJson($base, ['content' => '   '])->assertUnprocessable();

        $this->putJson("/api/v1/review-comments/{$id}", ['content' => '고쳤습니다'])
            ->assertOk()
            ->assertJsonPath('data.content', '고쳤습니다');
        $this->assertSame(1, $this->review->refresh()->comment_count);

        Sanctum::actingAs($this->author);
        $this->putJson("/api/v1/review-comments/{$id}", ['content' => '남의 댓글'])->assertForbidden();
        $this->deleteJson("/api/v1/review-comments/{$id}")->assertForbidden();

        Sanctum::actingAs($commenter);
        $this->deleteJson("/api/v1/review-comments/{$id}")->assertOk();
        $this->deleteJson("/api/v1/review-comments/{$id}")->assertNotFound();
        $this->assertSame(0, $this->review->refresh()->comment_count);
        $this->assertSame(5, $commenter->refresh()->point_balance, '삭제해도 회수하지 않는다');
    }

    public function test_comment_list_keeps_placeholders(): void
    {
        $other = User::factory()->create();
        ReviewComment::query()->create(['review_id' => $this->review->id, 'user_id' => $other->id, 'content' => '첫 댓글']);
        ReviewComment::query()->create(['review_id' => $this->review->id, 'user_id' => $other->id, 'content' => '지운 댓글', 'status' => CommentStatus::Deleted]);
        ReviewComment::query()->create(['review_id' => $this->review->id, 'user_id' => $other->id, 'content' => '숨긴 댓글', 'status' => CommentStatus::Hidden]);

        $this->getJson("/api/v1/reviews/{$this->review->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.items.0.content', '첫 댓글')
            ->assertJsonPath('data.items.0.is_mine', null)
            ->assertJsonPath('data.items.1.content', null)
            ->assertJsonPath('data.items.1.author', null)
            ->assertJsonPath('data.items.1.placeholder', '삭제된 댓글입니다.')
            ->assertJsonPath('data.items.2.placeholder', '관리자가 숨긴 댓글입니다.');

        $this->review->update(['status' => ReviewStatus::Deleted]);
        $this->getJson("/api/v1/reviews/{$this->review->id}/comments")->assertNotFound();
        Sanctum::actingAs($other);
        $this->postJson("/api/v1/reviews/{$this->review->id}/comments", ['content' => '안녕'])->assertNotFound();
    }
}
