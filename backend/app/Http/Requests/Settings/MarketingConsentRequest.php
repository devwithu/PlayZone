<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 마케팅 정보 수신 동의 변경 (REQ-607). 웹 설정 화면과 앱 API가 함께 쓴다.
 */
class MarketingConsentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** true: 동의, false: 철회 */
            'agreed' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['agreed' => '수신 동의'];
    }

    public function agreed(): bool
    {
        return $this->boolean('agreed');
    }
}
