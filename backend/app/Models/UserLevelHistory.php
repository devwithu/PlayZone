<?php

namespace App\Models;

use App\Enums\LevelChangeReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 등급 변경 이력 (ERD §8). UserLevelService만 추가한다.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $old_level_id
 * @property int|null $new_level_id
 * @property LevelChangeReason $reason
 * @property CarbonImmutable $created_at
 */
class UserLevelHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => LevelChangeReason::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<UserLevel, $this>
     */
    public function newLevel(): BelongsTo
    {
        return $this->belongsTo(UserLevel::class, 'new_level_id');
    }
}
