<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkSocialAccountRequest extends FormRequest
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
            /** 연결할 계정으로 네이티브 SDK에서 받은 ID 토큰 (JWT) */
            'id_token' => ['required', 'string', 'max:8192'],
            /** Apple만: 탈퇴·해제 시 토큰 폐기에 쓸 authorization code */
            'authorization_code' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
