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
            /** 소개. 비우면 지운다 */
            'introduction' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nickname' => '닉네임', 'introduction' => '소개'];
    }

    public function nickname(): string
    {
        return (string) $this->validated('nickname');
    }

    public function introduction(): ?string
    {
        $text = trim((string) $this->validated('introduction'));

        return $text === '' ? null : $text;
    }
}
