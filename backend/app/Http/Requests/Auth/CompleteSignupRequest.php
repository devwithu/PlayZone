<?php

namespace App\Http\Requests\Auth;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteSignupRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => $this->nicknameRules($this->user()->id),
            'age_confirmed' => ['accepted'],
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
            'marketing' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'age_confirmed.accepted' => '만 14세 미만은 가입할 수 없습니다.',
            'terms.accepted' => '이용약관에 동의해 주세요.',
            'privacy.accepted' => '개인정보 처리방침에 동의해 주세요.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nickname' => '닉네임'];
    }
}
