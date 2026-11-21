<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 리뷰의 세부 점수 (ERD §5). 입력한 항목만 행이 있다.
 *
 * @property int $id
 * @property int $review_id
 * @property int $rating_category_id
 * @property int $score
 * @property-read RatingCategory $category
 */
class ReviewRating extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    /**
     * @return BelongsTo<RatingCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(RatingCategory::class, 'rating_category_id');
    }
}
