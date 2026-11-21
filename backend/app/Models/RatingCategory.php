<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 세부 평가 항목 (ERD §5). 종합 점수는 game_reviews.overall_score에 있고 여기에는 세부 항목만 있다.
 * 비활성화하면 새 리뷰에서만 빠지고 기존 점수는 남는다.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'sort_order', 'is_active'])]
class RatingCategory extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<RatingCategory>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<RatingCategory>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
