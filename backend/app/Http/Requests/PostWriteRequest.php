<?php

namespace App\Http\Requests;

use App\Models\Board;
use App\Services\PostService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * 게시글 작성·수정 (REQ-403, 408, 화면 설계 W-12). 작성에는 게시판을 고르고, 수정은 게시판을 바꾸지 않는다.
 * 이미지: jpg·png·webp·gif, 1개 10MB 이하, 글당 10개 이하 (기존 첨부와 합친 개수는 PostService가 확인).
 */
class PostWriteRequest extends FormRequest
{
    public const IMAGE_MAX_KB = 10 * 1024;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->route('post') === null;

        return [
            'board_id' => $creating
                ? ['required', 'integer', Rule::exists(Board::class, 'id')->where('is_active', true)]
                : ['prohibited'],
            'title' => ['required', 'string', 'min:2', 'max:100'],
            'content' => ['required', 'string', 'max:20000'],
            'images' => ['sometimes', 'array', 'max:'.PostService::MAX_ATTACHMENTS],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif', 'mimetypes:image/jpeg,image/png,image/webp,image/gif', 'max:'.self::IMAGE_MAX_KB],
            'remove_attachment_ids' => ['sometimes', 'array'],
            'remove_attachment_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'board_id' => '게시판',
            'title' => '제목',
            'content' => '본문',
            'images' => '이미지',
            'images.*' => '이미지',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.max' => '이미지는 글 하나에 '.PostService::MAX_ATTACHMENTS.'개까지 올릴 수 있습니다.',
            'images.*.mimes' => 'jpg, png, webp, gif 이미지만 올릴 수 있습니다.',
            'images.*.mimetypes' => 'jpg, png, webp, gif 이미지만 올릴 수 있습니다.',
            'images.*.max' => '이미지 하나는 10MB 이하여야 합니다.',
        ];
    }

    public function board(): Board
    {
        return Board::query()->findOrFail((int) $this->validated('board_id'));
    }

    public function title(): string
    {
        return trim((string) $this->validated('title'));
    }

    public function body(): string
    {
        return trim((string) $this->validated('content'));
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        /** @var array<int, mixed>|UploadedFile|null $files */
        $files = $this->file('images');

        return array_values(array_filter(is_array($files) ? $files : [], fn ($f): bool => $f instanceof UploadedFile));
    }

    /**
     * @return list<int>
     */
    public function removeIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('remove_attachment_ids') ?? [];

        return array_values(array_map(intval(...), $ids));
    }
}
