<?php

namespace App\Enums;

/**
 * 댓글 상태 (리뷰 정책 §11). 숨김·삭제 댓글은 자리만 남기고 내용을 보여 주지 않는다.
 */
enum CommentStatus: string
{
    case Published = 'published';
    case Hidden = 'hidden';
    case Deleted = 'deleted';

    /** 내용 대신 보여 줄 문구. 공개 댓글이면 null */
    public function placeholder(): ?string
    {
        return match ($this) {
            self::Published => null,
            self::Hidden => '관리자가 숨긴 댓글입니다.',
            self::Deleted => '삭제된 댓글입니다.',
        };
    }
}
