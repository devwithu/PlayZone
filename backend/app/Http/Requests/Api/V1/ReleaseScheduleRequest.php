<?php

namespace App\Http\Requests\Api\V1;

use App\Services\ReleaseService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * 기간별 출시 일정 쿼리 (api-spec §7.4 GET /releases). 기간을 빼면 이번 달(KST).
 */
class ReleaseScheduleRequest extends FormRequest
{
    /** 한 번에 조회할 수 있는 최대 일수 (달력 한 화면 + 여유) */
    public const MAX_DAYS = 93;

    /** 쉼표로 이은 slug 목록 */
    public const SLUG_LIST = '/^[a-z0-9-]+(,[a-z0-9-]+)*$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** 시작일 `YYYY-MM-DD` (KST). `to`와 함께 준다 */
            'from' => ['required_with:to', 'date_format:Y-m-d'],
            /** 종료일 `YYYY-MM-DD` (KST, 포함). 시작일부터 93일 이내 */
            'to' => ['required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            /** 플랫폼 slug, 쉼표로 여러 개. 예: `ps5,pc` */
            'platform' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:'.self::SLUG_LIST],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || ! $this->filled(['from', 'to'])) {
                    return;
                }

                if ($this->from()->diffInDays($this->to()) >= self::MAX_DAYS) {
                    $validator->errors()->add('to', '기간은 '.self::MAX_DAYS.'일 이내로 지정해 주세요.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => '시작일', 'to' => '종료일', 'platform' => '플랫폼'];
    }

    public function from(): CarbonImmutable
    {
        return $this->filled('from')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('from')->value(), ReleaseService::TIMEZONE)
            : ReleaseService::today()->startOfMonth();
    }

    public function to(): CarbonImmutable
    {
        return $this->filled('to')
            ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('to')->value(), ReleaseService::TIMEZONE)
            : ReleaseService::today()->endOfMonth()->startOfDay();
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
