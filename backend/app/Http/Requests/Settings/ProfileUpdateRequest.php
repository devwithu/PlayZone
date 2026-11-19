<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nickname' => $this->nicknameRules($this->user()->id),
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
