<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PointType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * 포인트 거래 내역 쿼리 (api-spec §7.6 GET /points/history).
 */
class PointHistoryRequest extends PaginatedRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** 거래 사유로 거르기. 예: `attendance`, `review_create`, `event_entry` */
            'type' => ['sometimes', 'nullable', Rule::enum(PointType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type' => '거래 사유'];
    }

    public function type(): ?PointType
    {
        return $this->enum('type', PointType::class);
    }
}
