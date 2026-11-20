<?php

namespace App\Enums;

/** 게임 노출 상태 (ERD §4 games.status). 삭제 대신 hidden으로 숨긴다 */
enum GameStatus: string
{
    /** 관리자 작성 중. 사이트·API에 나오지 않는다 */
    case Draft = 'draft';

    case Published = 'published';

    /** 공개했다가 내린 게임. 리뷰 등 연결 데이터는 남긴다 */
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '작성 중',
            self::Published => '공개',
            self::Hidden => '숨김',
        };
    }
}
