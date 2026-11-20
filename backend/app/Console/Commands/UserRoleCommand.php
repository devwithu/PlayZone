<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * 관리 역할 부여·회수. 첫 관리자를 지정할 때 쓴다 (관리자 화면에서는 super_admin만 역할을 바꿀 수 있다).
 *
 * 예: php artisan user:role 1 super_admin / php artisan user:role 1 admin --remove
 */
#[Signature('user:role {user : 회원 ID 또는 이메일} {role : moderator, admin, super_admin} {--remove : 역할을 회수한다}')]
#[Description('회원에게 관리 역할을 부여하거나 회수합니다')]
class UserRoleCommand extends Command
{
    public function handle(UserRoleService $roles): int
    {
        $key = (string) $this->argument('user');
        $user = ctype_digit($key)
            ? User::query()->find((int) $key)
            : User::query()->where('email', mb_strtolower($key))->first();

        if (! $user instanceof User) {
            $this->error("회원을 찾을 수 없습니다: {$key}");

            return self::FAILURE;
        }

        $role = (string) $this->argument('role');
        if (! in_array($role, Role::PANEL_ROLES, true)) {
            $this->error('역할은 '.implode(', ', Role::PANEL_ROLES).' 중 하나여야 합니다.');

            return self::FAILURE;
        }

        try {
            $this->option('remove') ? $roles->revoke($user, $role) : $roles->grant($user, $role);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $current = $roles->panelRoles($user);
        $this->info(sprintf('회원 #%d (%s) 관리 역할: %s', $user->id, $user->getFilamentName(), $current === [] ? '없음' : implode(', ', $current)));

        return self::SUCCESS;
    }
}
