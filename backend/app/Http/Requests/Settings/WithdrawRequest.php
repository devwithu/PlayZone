<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 확인 화면의 "탈퇴합니다" 체크 (W-18)
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm.accepted' => '안내 내용을 확인하고 "탈퇴합니다"에 체크해 주세요.'];
    }
}
