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
use Illuminate\Support\Str;

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
     * 웹 주소 /games/{slug} (화면 설계 W-05). 영문 소문자·숫자·한글을 하이픈으로 잇는다.
     * 한글 주소는 네이버 검색 노출에 유리하다. 공개 후 바꾸면 기존 링크가 끊긴다.
     */
    public const SLUG_PATTERN = '/^[a-z0-9가-힣]+(?:-[a-z0-9가-힣]+)*$/u';

    /**
     * 제목으로 slug 후보를 만든다. 예: "별빛 원정대: 두 번째 이야기!" → "별빛-원정대-두-번째-이야기"
     */
    public static function slugFromTitle(string $title): string
    {
        // 언어를 지정하지 않아야 한글을 로마자로 바꾸지 않고 그대로 둔다
        return Str::slug($title, '-', null);
    }

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

    /**
     * 출시 예정 게임: 어느 플랫폼에도 출시되지 않았고, 취소되지 않은 출시 계획이 있다
     *
     * @param  Builder<Game>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->whereDoesntHave('releaseDates', fn (Builder $q) => $q->where('release_status', ReleaseStatus::Released))
            ->whereHas('releaseDates', fn (Builder $q) => $q->where('release_status', '!=', ReleaseStatus::Cancelled));
    }

    /**
     * 목록·카드에 보여 줄 대표 출시일 (releaseDates를 미리 불러 두고 쓴다).
     * 출시됐으면 가장 이른 출시일, 아니면 취소되지 않은 계획 중 가장 가까운 날(미정은 뒤로).
     */
    public function primaryRelease(): ?GameReleaseDate
    {
        $released = $this->releaseDates
            ->filter(fn (GameReleaseDate $r) => $r->release_status === ReleaseStatus::Released)
            ->sortBy(fn (GameReleaseDate $r) => $r->release_date?->toDateString());

        if ($released->isNotEmpty()) {
            return $released->first();
        }

        return $this->releaseDates
            ->filter(fn (GameReleaseDate $r) => $r->release_status !== ReleaseStatus::Cancelled)
            ->sortBy(fn (GameReleaseDate $r) => $r->release_date?->toDateString() ?? '9999-12-31')
            ->first();
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
