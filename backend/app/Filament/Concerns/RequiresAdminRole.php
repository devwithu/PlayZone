<?php

namespace App\Filament\Concerns;

use App\Models\Role;
use App\Models\User;

/**
 * "admin 이상"만 쓰는 리소스 (화면 설계 §관리자: ADM-02 게임, ADM-03 장르·플랫폼, ADM-06 회원).
 * 운영자(moderator)는 관리자 화면에 들어올 수 있지만 메뉴에 나오지 않고, 주소로 들어오면 403이다.
 */
trait RequiresAdminRole
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(...Role::ADMIN_ROLES);
    }
}
