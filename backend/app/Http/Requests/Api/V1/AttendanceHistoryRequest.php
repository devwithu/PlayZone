<?php

namespace App\Http\Requests\Api\V1;

use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 월별 출석 기록 쿼리 (api-spec §7.6 GET /attendance/history).
 */
class AttendanceHistoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** 조회할 달 (`YYYY-MM`, KST). 없으면 이번 달 */
            'month' => ['sometimes', 'nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['month' => '조회할 달'];
    }

    public function month(): CarbonImmutable
    {
        $month = $this->validated('month');

        return is_string($month)
            ? CarbonImmutable::createFromFormat('!Y-m', $month, ServiceDate::TIMEZONE) ?: ServiceDate::today()
            : ServiceDate::today();
    }
}
