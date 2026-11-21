<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 게임별 세부 항목 평균 (ERD §5, 집계 캐시). GameRatingService만 바꾼다.
 *
 * @property int $game_id
 * @property int $rating_category_id
 * @property float $avg_score
 * @property int $score_count 해당 항목에 점수를 준 공개 리뷰 수
 * @property-read RatingCategory $category
 */
class GameRatingStat extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'avg_score' => 'float',
            'score_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RatingCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RatingCategory::class, 'rating_category_id');
    }
}
