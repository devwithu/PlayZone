<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 랭킹 집계 한 줄 (ERD §8). RankingService만 쓰고 지운다.
 *
 * @property int $id
 * @property string $ranking_type
 * @property CarbonImmutable $snapshot_date
 * @property int $user_id
 * @property int $score
 * @property int $rank
 * @property CarbonImmutable $created_at
 * @property-read User $user
 */
class RankingSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'immutable_date',
            'score' => 'integer',
            'rank' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
