<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 게시글 (ERD §6). 상태 체계는 리뷰와 같다(ReviewStatus: published/blinded/hidden/deleted).
 * 작성·수정·삭제와 집계(view/like/comment_count)는 Post*Service로만 바꾼다.
 *
 * @property int $id
 * @property int $board_id
 * @property int $user_id
 * @property string $title
 * @property string $content
 * @property ReviewStatus $status
 * @property string|null $hidden_reason
 * @property int $view_count
 * @property int $like_count
 * @property int $comment_count
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Board $board
 * @property-read User $user
 * @property-read Collection<int, PostAttachment> $attachments
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'published',
        'view_count' => 0,
        'like_count' => 0,
        'comment_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'edited_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PostAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PostAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->status === ReviewStatus::Published;
    }

    /**
     * @param  Builder<Post>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', ReviewStatus::Published);
    }
}
