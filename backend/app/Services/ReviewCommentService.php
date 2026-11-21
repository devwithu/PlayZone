<?php

namespace App\Services;

use App\Data\WriteResult;
use App\Enums\ApiErrorCode;
use App\Enums\CommentStatus;
use App\Enums\PointType;
use App\Exceptions\ApiException;
use App\Models\GameReview;
use App\Models\ReviewComment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 리뷰 댓글 (리뷰 정책 §11). 한 단계만. 웹과 앱이 같이 쓴다.
 *
 * - comment_count는 공개 댓글 수다. 작성·삭제와 같은 트랜잭션에서 바꾼다.
 * - 작성 포인트(comment_create)는 키 `comment_create:{comment_id}`, 게시판 댓글과 일일 한도를 같이 센다.
 * - 삭제·숨김 댓글은 자리만 남기고 "삭제된 댓글입니다"처럼 보여 준다.
 */
class ReviewCommentService
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly PointService $points,
    ) {}

    /**
     * 공개 리뷰의 댓글 (오래된 순). 삭제·숨김 댓글도 자리를 지키도록 함께 준다.
     *
     * @return LengthAwarePaginator<int, ReviewComment>
     */
    public function listFor(int $reviewId, int $perPage): LengthAwarePaginator
    {
        $review = $this->reviews->findPublished($reviewId);

        return ReviewComment::query()
            ->with('user.level')
            ->where('review_id', $review->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @return WriteResult<ReviewComment>
     */
    public function create(User $user, int $reviewId, string $content): WriteResult
    {
        $review = $this->reviews->findPublished($reviewId);

        return DB::transaction(function () use ($user, $review, $content) {
            $comment = ReviewComment::query()->create([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'content' => $content,
            ]);
            GameReview::query()->whereKey($review->id)->increment('comment_count');

            $point = $this->points->earn(
                $user,
                PointType::CommentCreate,
                "comment_create:{$comment->id}",
                referenceType: 'review_comment',
                referenceId: $comment->id,
            );

            return new WriteResult($comment->load('user.level'), $point);
        });
    }

    public function update(User $user, ReviewComment $comment, string $content): ReviewComment
    {
        $this->assertEditable($user, $comment);

        $comment->update(['content' => $content]);

        return $comment->load('user.level');
    }

    /**
     * 작성자 삭제. 포인트는 회수하지 않는다(포인트 정책 §7).
     */
    public function delete(User $user, ReviewComment $comment): void
    {
        $this->assertEditable($user, $comment);

        DB::transaction(function () use ($comment) {
            $updated = ReviewComment::query()
                ->whereKey($comment->id)
                ->where('status', CommentStatus::Published)
                ->update(['status' => CommentStatus::Deleted, 'updated_at' => now()]);
            if ($updated > 0) {
                GameReview::query()->whereKey($comment->review_id)->decrement('comment_count');
            }
        });

        $comment->refresh();
    }

    /**
     * 본인의 공개 댓글이고, 리뷰도 공개 상태여야 수정·삭제할 수 있다.
     */
    private function assertEditable(User $user, ReviewComment $comment): void
    {
        $this->reviews->findPublished($comment->review_id);

        if ($comment->status === CommentStatus::Deleted) {
            throw ApiException::of(ApiErrorCode::NotFound, '댓글을 찾을 수 없습니다.');
        }
        if ($comment->user_id !== $user->id) {
            throw ApiException::of(ApiErrorCode::Forbidden, '본인 댓글만 수정·삭제할 수 있습니다.');
        }
        if ($comment->status !== CommentStatus::Published) {
            throw ApiException::of(ApiErrorCode::NotEditable, '숨겨진 댓글은 수정·삭제할 수 없습니다.');
        }
    }
}
