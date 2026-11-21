<?php

namespace App\Services;

use App\Data\ReviewInput;
use App\Data\WriteResult;
use App\Enums\ApiErrorCode;
use App\Enums\PointType;
use App\Enums\ReleaseStatus;
use App\Enums\ReviewSort;
use App\Enums\ReviewStatus;
use App\Exceptions\ApiException;
use App\Models\Game;
use App\Models\GameReview;
use App\Models\RatingCategory;
use App\Models\ReviewLike;
use App\Models\ReviewRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 리뷰 작성·수정·삭제·조회 (리뷰 정책 §2~§6, §9). 웹·앱 API·관리자가 같이 쓴다.
 *
 * - 게임당 리뷰 1개: 작성 전 확인하고, 동시 요청은 부분 UNIQUE 인덱스가 막는다.
 * - 작성 포인트(review_create)는 작성 즉시, 키 `review_create:{user_id}:{game_id}`로 한 번만 준다. 지우고 다시 써도 다시 받지 않는다.
 * - 점수가 바뀌는 변경은 커밋 뒤 게임 평균을 다시 계산한다(GameRatingService).
 * - 정지 회원은 미들웨어(signup.completed)가 먼저 막는다. 리뷰 작성 제한(REVIEW_BANNED)은 제재 기능에서 붙인다.
 */
class ReviewService
{
    /** 목록·상세에 함께 불러올 관계 */
    public const WITH = ['user.level', 'platform', 'ratings.category'];

    public function __construct(
        private readonly PointService $points,
        private readonly GameRatingService $ratings,
    ) {}

    /**
     * 이 회원이 이 게임에 새 리뷰를 쓸 수 있는지 확인한다. 실패 순서는 api-spec §7.3을 따른다.
     */
    public function assertCanWrite(User $user, Game $game): void
    {
        if (! $this->isReleased($game)) {
            throw ApiException::of(ApiErrorCode::GameNotReleased, '출시 전 게임에는 리뷰를 쓸 수 없습니다.');
        }

        $existing = GameReview::query()->notDeleted()->where('user_id', $user->id)->where('game_id', $game->id)->first();
        if ($existing?->status === ReviewStatus::Hidden) {
            throw ApiException::of(ApiErrorCode::ReviewHiddenExists);
        }
        if ($existing !== null) {
            throw ApiException::of(ApiErrorCode::ReviewAlreadyExists, errors: ['review_id' => $existing->id]);
        }
    }

    /**
     * 리뷰를 쓸 수 있는 플랫폼: 그 게임이 출시된 플랫폼 (리뷰 정책 §4)
     *
     * @return list<int>
     */
    public function releasedPlatformIds(Game $game): array
    {
        return array_values($game->releaseDates()
            ->where('release_status', ReleaseStatus::Released)
            ->distinct()
            ->orderBy('platform_id')
            ->pluck('platform_id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    /**
     * @return WriteResult<GameReview>
     */
    public function create(User $user, Game $game, ReviewInput $input): WriteResult
    {
        $this->assertCanWrite($user, $game);

        try {
            $result = DB::transaction(function () use ($user, $game, $input) {
                $review = GameReview::query()->create([
                    'game_id' => $game->id,
                    'user_id' => $user->id,
                    ...$this->attributes($input),
                ]);
                $this->saveRatings($review, $input->ratings);

                $point = $this->points->earn(
                    $user,
                    PointType::ReviewCreate,
                    "review_create:{$user->id}:{$game->id}",
                    referenceType: 'review',
                    referenceId: $review->id,
                );

                $this->ratings->recalculateAfterCommit($game->id);

                return new WriteResult($review, $point);
            });
        } catch (UniqueConstraintViolationException) {
            // 같은 회원이 같은 게임에 동시에 두 번 보낸 경우
            $this->assertCanWrite($user, $game);
            throw ApiException::of(ApiErrorCode::ReviewAlreadyExists);
        }

        /** @var GameReview $review */
        $review = $result->model;
        $review->load(self::WITH);

        return $result;
    }

    /**
     * 수정 (리뷰 정책 §6). 공개 상태인 본인 리뷰만. 수정 전 내용을 이력으로 남긴다. 포인트는 없다.
     */
    public function update(User $user, GameReview $review, ReviewInput $input): GameReview
    {
        $this->assertOwner($user, $review);

        DB::transaction(function () use ($review, $input) {
            /** @var GameReview $locked */
            $locked = GameReview::query()->with('ratings.category')->lockForUpdate()->findOrFail($review->id);
            if (! $locked->isPublished()) {
                throw ApiException::of(ApiErrorCode::NotEditable, '검토 중이거나 숨겨진 리뷰는 수정할 수 없습니다.');
            }

            $locked->revisions()->create([
                'title' => $locked->title,
                'content' => $locked->content,
                'overall_score' => $locked->overall_score,
                'platform_id' => $locked->platform_id,
                'has_spoiler' => $locked->has_spoiler,
                'ratings' => $locked->ratingMap(),
            ]);

            $locked->fill([...$this->attributes($input), 'edited_at' => now()])->save();
            $this->saveRatings($locked, $input->ratings, replace: true);

            $this->ratings->recalculateAfterCommit($locked->game_id);
        });

        return $review->refresh()->load(self::WITH);
    }

    /**
     * 작성자 삭제 (리뷰 정책 §5·§6). 상태만 deleted로 바꾼다. 포인트는 회수하지 않는다.
     * 검토 중·숨김 리뷰는 지울 수 없다(숨김 리뷰를 지우고 새로 쓰는 것을 막기 위해).
     */
    public function delete(User $user, GameReview $review): void
    {
        $this->assertOwner($user, $review);

        DB::transaction(function () use ($review) {
            $locked = GameReview::query()->lockForUpdate()->findOrFail($review->id);
            if ($locked->status === ReviewStatus::Deleted) {
                throw ApiException::of(ApiErrorCode::NotFound);
            }
            if (! $locked->isPublished()) {
                throw ApiException::of(ApiErrorCode::NotEditable, '검토 중이거나 숨겨진 리뷰는 삭제할 수 없습니다.');
            }

            $locked->update(['status' => ReviewStatus::Deleted]);
            $this->ratings->recalculateAfterCommit($locked->game_id);
        });

        $review->refresh();
    }

    /**
     * 상세 조회. 공개 리뷰는 누구나, 검토 중·숨김 리뷰는 작성자 본인만 본다. 삭제된 리뷰와 비공개 게임의 리뷰는 404.
     */
    public function findVisible(int $id, ?User $viewer): GameReview
    {
        $review = GameReview::query()
            ->with([...self::WITH, 'game'])
            ->notDeleted()
            ->whereHas('game', fn (Builder $q) => $q->published())
            ->find($id);

        if ($review === null || (! $review->isPublished() && $review->user_id !== $viewer?->id)) {
            throw ApiException::of(ApiErrorCode::NotFound, '리뷰를 찾을 수 없습니다.');
        }

        return $review;
    }

    /**
     * 수정·삭제 대상 리뷰 (상태 무관). 없으면 404. 본인·상태 확인은 update()·delete()가 한다.
     */
    public function find(int $id): GameReview
    {
        return GameReview::query()->find($id) ?? throw ApiException::of(ApiErrorCode::NotFound, '리뷰를 찾을 수 없습니다.');
    }

    /**
     * 추천·댓글 대상 리뷰: 공개 게임의 공개 리뷰만. 아니면 404.
     */
    public function findPublished(int $id): GameReview
    {
        $review = GameReview::query()
            ->published()
            ->whereHas('game', fn (Builder $q) => $q->published())
            ->find($id);

        return $review ?? throw ApiException::of(ApiErrorCode::NotFound, '리뷰를 찾을 수 없습니다.');
    }

    /**
     * 게임의 공개 리뷰 목록 (리뷰 정책 §9 정렬).
     *
     * @return LengthAwarePaginator<int, GameReview>
     */
    public function listForGame(Game $game, ReviewSort $sort, int $perPage): LengthAwarePaginator
    {
        $query = GameReview::query()->with(self::WITH)->where('game_id', $game->id)->published();

        match ($sort) {
            ReviewSort::Likes => $query->orderByDesc('like_count')->orderByDesc('id'),
            ReviewSort::Latest => $query->orderByDesc('created_at')->orderByDesc('id'),
            ReviewSort::ScoreHigh => $query->orderByDesc('overall_score')->orderByDesc('id'),
            ReviewSort::ScoreLow => $query->orderBy('overall_score')->orderByDesc('id'),
        };

        return $query->paginate($perPage);
    }

    /**
     * 내가 이 게임에 쓴 리뷰 ID (삭제한 리뷰 제외). 비회원이면 null.
     */
    public function myReviewId(?User $user, Game $game): ?int
    {
        if ($user === null) {
            return null;
        }

        $id = GameReview::query()->notDeleted()->where('user_id', $user->id)->where('game_id', $game->id)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * 이 회원이 추천한 리뷰 ID 목록 (목록 화면의 추천 표시용)
     *
     * @param  array<int, int>  $reviewIds
     * @return list<int>
     */
    public function likedIds(?User $user, array $reviewIds): array
    {
        if ($user === null || $reviewIds === []) {
            return [];
        }

        return array_values(ReviewLike::query()
            ->where('user_id', $user->id)
            ->whereIn('review_id', $reviewIds)
            ->pluck('review_id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }

    private function isReleased(Game $game): bool
    {
        return $game->releaseDates()->where('release_status', ReleaseStatus::Released)->exists();
    }

    private function assertOwner(User $user, GameReview $review): void
    {
        if ($review->status === ReviewStatus::Deleted) {
            throw ApiException::of(ApiErrorCode::NotFound, '리뷰를 찾을 수 없습니다.');
        }
        if ($review->user_id !== $user->id) {
            throw ApiException::of(ApiErrorCode::Forbidden, '본인 리뷰만 수정·삭제할 수 있습니다.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(ReviewInput $input): array
    {
        return [
            'platform_id' => $input->platformId,
            'title' => $input->title,
            'content' => $input->content,
            'overall_score' => $input->overallScore,
            'has_spoiler' => $input->hasSpoiler,
        ];
    }

    /**
     * 세부 점수를 저장한다. 수정이면 활성 항목의 점수만 새로 바꾸고, 비활성 항목의 예전 점수는 남긴다(리뷰 정책 §4).
     *
     * @param  array<string, int>  $ratings
     */
    private function saveRatings(GameReview $review, array $ratings, bool $replace = false): void
    {
        $categories = RatingCategory::query()->active()->pluck('id', 'slug');

        if ($replace) {
            ReviewRating::query()->where('review_id', $review->id)->whereIn('rating_category_id', $categories->values())->delete();
        }

        foreach ($ratings as $slug => $score) {
            if (isset($categories[$slug])) {
                ReviewRating::query()->create([
                    'review_id' => $review->id,
                    'rating_category_id' => $categories[$slug],
                    'score' => $score,
                ]);
            }
        }
    }
}
