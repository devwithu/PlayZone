<?php

namespace App\Enums;

/**
 * 리뷰 상태 (리뷰 정책 §5). 공개(published)만 목록·평균에 들어간다.
 */
enum ReviewStatus: string
{
    case Published = 'published';
    /** 신고 누적으로 자동 블라인드, 관리자 검토 대기 */
    case Blinded = 'blinded';
    /** 관리자 숨김 (위반 확정) */
    case Hidden = 'hidden';
    /** 작성자 삭제 */
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Published => '공개',
            self::Blinded => '검토 중',
            self::Hidden => '숨김',
            self::Deleted => '삭제',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Published => 'success',
            self::Blinded => 'warning',
            self::Hidden => 'danger',
            self::Deleted => 'gray',
        };
    }
}
