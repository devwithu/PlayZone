<?php

namespace App\Models;

use App\Enums\GameImageType;
use App\Enums\GameStatus;
use App\Enums\ReleaseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 게임 (ERD §4). 삭제하지 않고 status를 hidden으로 바꾼다.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string|null $developer
 * @property string|null $publisher
 * @property GameStatus $status
 * @property int $review_count 공개 리뷰 수 (집계 캐시)
 * @property float|null $rating_avg 종합 점수 평균. 공개 리뷰 3개 미만이면 NULL (집계 캐시)
 * @property int $favorite_count 집계 캐시
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'slug', 'description', 'developer', 'publisher', 'status'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'review_count' => 0,
        'favorite_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'review_count' => 'integer',
            'rating_avg' => 'float',
            'favorite_count' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Genre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->orderBy('sort_order')->orderBy('genres.id');
    }

    /**
     * @return BelongsToMany<Platform, $this>
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class)->orderBy('sort_order')->orderBy('platforms.id');
    }

    /**
     * @return HasMany<GameImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(GameImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasOne<GameImage, $this>
     */
    public function cover(): HasOne
    {
        return $this->hasOne(GameImage::class)->where('image_type', GameImageType::Cover);
    }

    /**
     * @return HasMany<GameReleaseDate, $this>
     */
    public function releaseDates(): HasMany
    {
        return $this->hasMany(GameReleaseDate::class);
    }

    /**
     * 사이트·API에 보여 줄 게임
     *
     * @param  Builder<Game>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', GameStatus::Published);
    }

    /**
     * 리뷰를 쓸 수 있는 게임: 한 플랫폼이라도 출시됨 (리뷰 정책 §2)
     *
     * @param  Builder<Game>  $query
     */
    #[Scope]
    protected function reviewable(Builder $query): void
    {
        $query->whereHas('releaseDates', fn (Builder $q) => $q->where('release_status', ReleaseStatus::Released));
    }

    public function isPublished(): bool
    {
        return $this->status === GameStatus::Published;
    }

    public function isReviewable(): bool
    {
        return $this->releaseDates()->where('release_status', ReleaseStatus::Released)->exists();
    }
}
