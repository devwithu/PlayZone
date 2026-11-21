<?php

namespace App\Data;

/**
 * 리뷰 작성·수정 입력 (리뷰 정책 §4). ReviewWriteRequest가 검증한 뒤 만든다.
 */
final readonly class ReviewInput
{
    /**
     * @param  array<string, int>  $ratings  세부 점수 {slug: score}. 입력한 항목만
     */
    public function __construct(
        public string $title,
        public string $content,
        public int $platformId,
        public int $overallScore,
        public array $ratings = [],
        public bool $hasSpoiler = false,
    ) {}
}
