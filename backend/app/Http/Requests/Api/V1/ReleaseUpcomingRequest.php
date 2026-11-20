<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 출시 예정 쿼리 (api-spec §7.4 GET /releases/upcoming).
 */
class ReleaseUpcomingRequest extends PaginatedRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** 플랫폼 slug, 쉼표로 여러 개. 예: `ps5,pc` */
            'platform' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:'.ReleaseScheduleRequest::SLUG_LIST],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['platform' => '플랫폼'];
    }

    /**
     * @return list<string>
     */
    public function platforms(): array
    {
        $value = $this->string('platform')->value();

        return $value === '' ? [] : array_values(array_unique(explode(',', $value)));
    }
}
