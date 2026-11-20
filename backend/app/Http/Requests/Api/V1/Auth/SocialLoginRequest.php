<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** 네이티브 SDK로 받은 ID 토큰 (JWT) */
            'id_token' => ['required', 'string', 'max:8192'],
            /** Apple만: 탈퇴 시 토큰 폐기에 쓸 authorization code */
            'authorization_code' => ['nullable', 'string', 'max:1024'],
            /** 기기 이름 (예: Unity Android). 같은 이름으로 다시 로그인하면 이전 토큰을 폐기한다 */
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
