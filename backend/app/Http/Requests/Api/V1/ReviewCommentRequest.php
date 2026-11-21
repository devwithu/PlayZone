<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 리뷰 댓글 작성·수정 (리뷰 정책 §11). 웹과 앱이 같이 쓴다.
 */
class ReviewCommentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** 댓글 1~500자 */
            'content' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['content' => '댓글'];
    }

    public function commentContent(): string
    {
        return trim((string) $this->validated('content'));
    }
}
