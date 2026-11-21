<?php

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Enums\PointType;
use App\Exceptions\ApiException;
use App\Models\GameReview;
use App\Models\ReviewLike;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 리뷰 추천 (리뷰 정책 §10, 포인트 정책 §3·§5). 웹과 앱이 같이 쓴다.
 *
 * - 회원·리뷰당 한 번: UNIQUE (user_id, review_id) + insertOrIgnore. 추천·취소는 멱등이다.
 * - like_count는 실제로 추가·삭제된 행 수만큼만 같은 트랜잭션에서 바꾼다.
 * - 작성자 포인트(review_like_received)는 키 `review_like_received:{review_id}:{liker_id}`로 한 번만. 취소 후 다시 추천해도 다시 주지 않는다.
 */
class ReviewLikeService
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly PointService $points,
    ) {}

    /**
     * @return array{liked: bool, like_count: int}
     */
    public function like(User $user, int $reviewId): array
    {
        $review = $this->reviews->findPublished($reviewId);
        if ($review->user_id === $user->id) {
            throw ApiException::of(ApiErrorCode::CannotTargetOwn, '본인 리뷰는 추천할 수 없습니다.');
        }

        DB::transaction(function () use ($user, $review) {
            $inserted = ReviewLike::query()->insertOrIgnore([
                'review_id' => $review->id,
                'user_id' => $user->id,
                'created_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }

            GameReview::query()->whereKey($review->id)->increment('like_count');
            $this->points->earn(
                $review->user,
                PointType::ReviewLikeReceived,
                "review_like_received:{$review->id}:{$user->id}",
                referenceType: 'review',
                referenceId: $review->id,
            );
        });

        return $this->state($user, $review->id);
    }

    /**
     * 추천 취소. 추천하지 않았어도 성공한다. 공개되지 않은 리뷰도 취소는 된다.
     *
     * @return array{liked: bool, like_count: int}
     */
    public function unlike(User $user, int $reviewId): array
    {
        $review = GameReview::query()->notDeleted()->find($reviewId)
            ?? throw ApiException::of(ApiErrorCode::NotFound, '리뷰를 찾을 수 없습니다.');

        DB::transaction(function () use ($user, $review) {
            $deleted = ReviewLike::query()->where('review_id', $review->id)->where('user_id', $user->id)->delete();
            if ($deleted > 0) {
                GameReview::query()->whereKey($review->id)->decrement('like_count', $deleted);
            }
        });

        return $this->state($user, $review->id);
    }

    /**
     * @return array{liked: bool, like_count: int}
     */
    private function state(User $user, int $reviewId): array
    {
        return [
            'liked' => ReviewLike::query()->where('review_id', $reviewId)->where('user_id', $user->id)->exists(),
            'like_count' => (int) GameReview::query()->whereKey($reviewId)->value('like_count'),
        ];
    }
}
