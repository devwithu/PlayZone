<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\GameReleaseFilter;
use App\Enums\GameSort;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * 게임 목록 쿼리 (api-spec §7.2). 웹 게임 목록 화면도 같은 규칙을 쓴다.
 */
class GameIndexRequest extends PaginatedRequest
{
    /** 쉼표로 이은 slug 목록 (예: rpg,action) */
    private const SLUG_LIST = '/^[a-z0-9-]+(,[a-z0-9-]+)*$/';

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** 검색어 (제목 중간 일치, 2자 이상) */
            'q' => ['sometimes', 'nullable', 'string', 'min:2', 'max:100'],
            /** 장르 slug, 쉼표로 여러 개 (하나라도 해당하면 포함). 예: `rpg,action` */
            'genre' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:'.self::SLUG_LIST],
            /** 플랫폼 slug, 쉼표로 여러 개. 예: `ps5,pc` */
            'platform' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:'.self::SLUG_LIST],
            'release_status' => ['sometimes', 'nullable', Rule::enum(GameReleaseFilter::class)],
            'sort' => ['sometimes', 'nullable', Rule::enum(GameSort::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'q' => '검색어',
            'genre' => '장르',
            'platform' => '플랫폼',
            'release_status' => '출시 여부',
            'sort' => '정렬',
        ];
    }

    public function keyword(): ?string
    {
        $keyword = trim($this->string('q')->value());

        return $keyword === '' ? null : $keyword;
    }

    /**
     * @return list<string>
     */
    public function slugs(string $key): array
    {
        $value = $this->string($key)->value();

        return $value === '' ? [] : array_values(array_unique(explode(',', $value)));
    }

    public function releaseFilter(): ?GameReleaseFilter
    {
        return $this->enum('release_status', GameReleaseFilter::class);
    }

    public function sort(): GameSort
    {
        return $this->enum('sort', GameSort::class) ?? GameSort::Popular;
    }
}
