<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ReviewSort;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * 게임 리뷰 목록 쿼리 (api-spec §7.3 GET /games/{id}/reviews).
 */
class ReviewIndexRequest extends PaginatedRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** 정렬: `likes`(추천순, 기본), `latest`, `score_high`, `score_low` */
            'sort' => ['sometimes', 'nullable', Rule::enum(ReviewSort::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['sort' => '정렬'];
    }

    public function sort(): ReviewSort
    {
        return $this->enum('sort', ReviewSort::class) ?? ReviewSort::Likes;
    }
}
