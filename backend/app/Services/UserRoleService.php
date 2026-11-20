<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 관리 역할(moderator/admin/super_admin) 부여·회수 (ERD §3, ADR-003 §6).
 * 관리자 화면에서는 super_admin만 바꿀 수 있고, 첫 관리자는 `php artisan user:role`로 지정한다.
 */
class UserRoleService
{
    /**
     * @param  list<string>  $slugs  부여할 관리 역할 전체. 빠진 관리 역할은 회수한다
     */
    public function syncPanelRoles(User $target, array $slugs, ?User $actor = null): void
    {
        $unknown = array_diff($slugs, Role::PANEL_ROLES);
        if ($unknown !== []) {
            throw new InvalidArgumentException('관리 역할이 아닙니다: '.implode(', ', $unknown));
        }

        if ($slugs !== [] && ! $target->isActive()) {
            throw new InvalidArgumentException('정상 회원에게만 관리 역할을 줄 수 있습니다.');
        }

        // 최고 관리자가 실수로 자기 권한을 없애 아무도 역할을 바꿀 수 없게 되는 것을 막는다
        if ($actor?->is($target) && $actor->hasRole(Role::SUPER_ADMIN) && ! in_array(Role::SUPER_ADMIN, $slugs, true)) {
            throw new InvalidArgumentException('자기 자신의 최고 관리자 역할은 해제할 수 없습니다.');
        }

        DB::transaction(function () use ($target, $slugs) {
            $panelRoleIds = Role::query()->whereIn('slug', Role::PANEL_ROLES)->pluck('id');
            $grantIds = Role::query()->whereIn('slug', $slugs)->pluck('id');

            $target->roles()->detach($panelRoleIds->diff($grantIds)->all());
            $target->roles()->syncWithoutDetaching($grantIds->all());
        });

        $target->unsetRelation('roles');
    }

    public function grant(User $target, string $slug): void
    {
        $this->syncPanelRoles($target, array_values(array_unique([...$this->panelRoles($target), $slug])));
    }

    public function revoke(User $target, string $slug): void
    {
        $this->syncPanelRoles($target, array_values(array_diff($this->panelRoles($target), [$slug])));
    }

    /**
     * @return list<string>
     */
    public function panelRoles(User $user): array
    {
        /** @var list<string> */
        return $user->roles()->whereIn('slug', Role::PANEL_ROLES)->orderBy('roles.id')->pluck('slug')->all();
    }
}
