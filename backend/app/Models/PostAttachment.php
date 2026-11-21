<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 게시글 첨부 이미지 (REQ-408). 상대 경로만 저장하고 URL은 Storage::url()로 만든다. 행을 지우면 파일도 지운다.
 *
 * @property int $id
 * @property int $post_id
 * @property string $file_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class PostAttachment extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleted(fn (PostAttachment $attachment) => Storage::delete($attachment->file_path));
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function url(): string
    {
        return Storage::url($this->file_path);
    }
}
