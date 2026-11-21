<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 리뷰 댓글 (리뷰 정책 §11). 한 단계만. 삭제·숨김은 상태만 바꾼다. ReviewCommentService로만 바꾼다.
 *
 * @property int $id
 * @property int $review_id
 * @property int $user_id
 * @property string $content
 * @property CommentStatus $status
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read GameReview $review
 * @property-read User $user
 */
class ReviewComment extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'published',
    ];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<GameReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(GameReview::class, 'review_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEdited(): bool
    {
        return $this->updated_at->gt($this->created_at);
    }
}
