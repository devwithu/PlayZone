<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * 리뷰 수정 이력 (리뷰 정책 §6). 수정 **전** 내용을 남긴다. 바꾸지 않는다.
 *
 * @property int $id
 * @property int $review_id
 * @property string $title
 * @property string $content
 * @property int $overall_score
 * @property int $platform_id
 * @property bool $has_spoiler
 * @property array<string, int> $ratings 세부 점수 {slug: score}
 * @property CarbonImmutable $created_at
 */
class ReviewRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('리뷰 수정 이력은 바꿀 수 없습니다.'));
    }

    protected function casts(): array
    {
        return [
            'overall_score' => 'integer',
            'has_spoiler' => 'boolean',
            'ratings' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
