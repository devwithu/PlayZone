<?php

namespace App\Enums;

/**
 * 리뷰 목록 정렬 (리뷰 정책 §9, api-spec §7.3).
 */
enum ReviewSort: string
{
    /** 추천순 (기본) */
    case Likes = 'likes';
    case Latest = 'latest';
    case ScoreHigh = 'score_high';
    case ScoreLow = 'score_low';

    public function label(): string
    {
        return match ($this) {
            self::Likes => '추천순',
            self::Latest => '최신순',
            self::ScoreHigh => '점수 높은순',
            self::ScoreLow => '점수 낮은순',
        };
    }
}
