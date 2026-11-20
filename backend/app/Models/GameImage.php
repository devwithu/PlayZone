<?php

namespace App\Models;

use App\Enums\GameImageType;
use Carbon\CarbonImmutable;
use Database\Factories\GameImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 게임 이미지. 파일은 저장소 기준 상대 경로만 저장하고 URL은 Storage::url()로 만든다 (ERD §1 파일).
 *
 * @property int $id
 * @property int $game_id
 * @property string $image_path 예: games/15/cover.webp
 * @property GameImageType $image_type
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['image_path', 'image_type', 'sort_order'])]
class GameImage extends Model
{
    /** @use HasFactory<GameImageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'image_type' => GameImageType::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // 행을 지우거나 파일을 바꾸면 이전 파일도 지운다 (DB에는 상대 경로만 있으므로 디스크가 바뀌어도 그대로 동작)
        static::updated(function (GameImage $image) {
            $previous = $image->getOriginal('image_path');
            if ($image->wasChanged('image_path') && is_string($previous) && $previous !== '') {
                Storage::delete($previous);
            }
        });

        static::deleted(fn (GameImage $image) => Storage::delete($image->image_path));
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function url(): string
    {
        return Storage::url($this->image_path);
    }
}
