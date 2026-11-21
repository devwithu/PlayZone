<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * 리뷰·댓글 작성자 표시 (리뷰 정책 §9). 탈퇴한 회원은 "탈퇴한 회원"으로 보여 주고 ID를 숨긴다.
 * user.level을 미리 불러 두면 쿼리가 늘지 않는다.
 */
final class Author
{
    public const WITHDRAWN_NAME = '탈퇴한 회원';

    /**
     * @return array{id: int|null, nickname: string, level: array{name: string, icon_url: string|null}|null}
     */
    public static function from(User $user): array
    {
        if ($user->status === UserStatus::Withdrawn) {
            return ['id' => null, 'nickname' => self::WITHDRAWN_NAME, 'level' => null];
        }

        return [
            'id' => $user->id,
            'nickname' => (string) $user->nickname,
            'level' => $user->level === null ? null : ['name' => $user->level->name, 'icon_url' => $user->level->iconUrl()],
        ];
    }
}
