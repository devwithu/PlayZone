<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * 프로필 이미지 업로드 (REQ-602). jpg·png·webp, 5MB 이하, 가로·세로 100px 이상. 웹 설정 화면과 앱 API가 함께 쓴다.
 */
class ProfileImageRequest extends FormRequest
{
    public const MAX_KB = 5 * 1024;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /** 이미지 파일 (multipart) */
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.self::MAX_KB,
                'dimensions:min_width=100,min_height=100',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimes' => 'jpg, png, webp 이미지만 올릴 수 있습니다.',
            'image.mimetypes' => 'jpg, png, webp 이미지만 올릴 수 있습니다.',
            'image.max' => '프로필 이미지는 5MB 이하여야 합니다.',
            'image.dimensions' => '가로·세로 100px 이상인 이미지를 올려 주세요.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['image' => '프로필 이미지'];
    }

    public function imageFile(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('image');
    }
}
