<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GameReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 게임 리뷰 (ERD §5, 리뷰 정책). 회원당 게임별로 삭제되지 않은 리뷰 1개.
 * 작성·수정·삭제와 집계(like_count, comment_count, 게임 평균)는 Review*Service로만 바꾼다.
 *
 * @property int $id
 * @property int $game_id
 * @property int $user_id
 * @property int $platform_id
 * @property string $title
 * @property string $content
 * @property int $overall_score
 * @property bool $has_spoiler
 * @property ReviewStatus $status
 * @property string|null $hidden_reason
 * @property int $like_count
 * @property int $comment_count
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Game $game
 * @property-read User $user
 * @property-read Platform $platform
 * @property-read Collection<int, ReviewRating> $ratings
 */
class GameReview extends Model
{
    /** @use HasFactory<GameReviewFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'published',
        'has_spoiler' => false,
        'like_count' => 0,
        'comment_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'overall_score' => 'integer',
            'has_spoiler' => 'boolean',
            'status' => ReviewStatus::class,
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'edited_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * @return HasMany<ReviewRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(ReviewRating::class, 'review_id');
    }

    /**
     * @return HasMany<ReviewComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class, 'review_id');
    }

    /**
     * @return HasMany<ReviewRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(ReviewRevision::class, 'review_id');
    }

    /**
     * 세부 점수 {slug: score} (항목 순서대로). ratings.category를 미리 불러 두면 쿼리가 늘지 않는다.
     *
     * @return array<string, int>
     */
    public function ratingMap(): array
    {
        $map = [];
        foreach ($this->ratings->sortBy(fn (ReviewRating $r) => $r->category->sort_order * 100000 + $r->category->id) as $rating) {
            $map[$rating->category->slug] = $rating->score;
        }

        return $map;
    }

    public function isPublished(): bool
    {
        return $this->status === ReviewStatus::Published;
    }

    /**
     * @param  Builder<GameReview>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ReviewStatus::Published);
    }

    /**
     * 작성자가 지우지 않은 리뷰 (게임당 1개 규칙의 대상)
     *
     * @param  Builder<GameReview>  $query
     */
    #[Scope]
    protected function notDeleted(Builder $query): void
    {
        $query->where('status', '<>', ReviewStatus::Deleted);
    }
}
