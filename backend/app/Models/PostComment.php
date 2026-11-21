<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 게시글 댓글 (ERD §6). 한 단계만. 삭제·숨김은 상태만 바꾼다. PostCommentService로만 바꾼다.
 *
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property string $content
 * @property CommentStatus $status
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Post $post
 * @property-read User $user
 */
class PostComment extends Model
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
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
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
