<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * 리뷰 추천 (ERD §5). 회원·리뷰당 하나. 취소하면 행을 지운다. ReviewLikeService로만 바꾼다.
 *
 * @property int $id
 * @property int $review_id
 * @property int $user_id
 * @property CarbonImmutable $created_at
 */
class ReviewLike extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
}
