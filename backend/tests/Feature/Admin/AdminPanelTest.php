<?php

namespace Tests\Feature\Admin;

use App\Enums\LoginProvider;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 관리자 화면 접근과 회원 관리 (ADR-003 §6)
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function userWithRole(string ...$slugs): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->whereIn('slug', $slugs)->pluck('id'));

        return $user;
    }

    // ---- 접근 ----

    public function test_guest_is_sent_to_web_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_member_without_panel_role_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(Role::MEMBER))->get('/admin')->assertForbidden();
    }

    public function test_pending_or_suspended_user_with_role_is_forbidden(): void
    {
        $pending = User::factory()->pending()->create();
        $pending->roles()->attach(Role::query()->where('slug', Role::ADMIN)->value('id'));
        $suspended = User::factory()->suspended()->create();
        $suspended->roles()->attach(Role::query()->where('slug', Role::ADMIN)->value('id'));

        $this->actingAs($pending)->get('/admin')->assertForbidden();
        $this->actingAs($suspended)->get('/admin')->assertForbidden();
    }

    public function test_panel_roles_can_open_admin(): void
    {
        foreach (Role::PANEL_ROLES as $slug) {
            $this->actingAs($this->userWithRole($slug))->get('/admin')->assertOk();
        }
    }

    public function test_admin_has_no_own_login_page(): void
    {
        $this->get('/admin/login')->assertNotFound();
    }

    // ---- 회원 목록·상세 ----

    public function test_admin_lists_and_searches_members(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $zelda = User::factory()->create(['nickname' => '젤다러버']);
        SocialAccount::factory()->for($zelda)->create(['provider' => LoginProvider::Apple]);
        $mario = User::factory()->create(['nickname' => '마리오팬']);
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$zelda, $mario])
            ->searchTable('젤다')
            ->assertCanSeeTableRecords([$zelda])
            ->assertCanNotSeeTableRecords([$mario]);

        Livewire::test(ListUsers::class)
            ->filterTable('provider', 'apple')
            ->assertCanSeeTableRecords([$zelda])
            ->assertCanNotSeeTableRecords([$mario]);
    }

    public function test_admin_views_member_detail(): void
    {
        $member = User::factory()->create(['nickname' => '젤다러버']);
        $this->actingAs($this->userWithRole(Role::MODERATOR));

        $this->get("/admin/users/{$member->id}")->assertOk()->assertSee('젤다러버');
    }

    public function test_members_cannot_be_created_or_edited_from_admin(): void
    {
        $member = User::factory()->create();
        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN));

        $this->get('/admin/users/create')->assertNotFound();
        $this->get("/admin/users/{$member->id}/edit")->assertNotFound();
    }

    // ---- 관리 역할 변경 ----

    public function test_only_super_admin_sees_role_action(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->userWithRole(Role::ADMIN));
        Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])->assertActionHidden('changeRoles');

        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN));
        Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])->assertActionVisible('changeRoles');
    }

    public function test_super_admin_changes_member_roles(): void
    {
        $member = $this->userWithRole(Role::MEMBER, Role::MODERATOR);
        $this->actingAs($this->userWithRole(Role::SUPER_ADMIN));

        Livewire::test(ViewUser::class, ['record' => $member->getRouteKey()])
            ->callAction('changeRoles', ['roles' => [Role::ADMIN]])
            ->assertHasNoActionErrors()
            ->assertNotified('관리 역할을 변경했습니다.');

        // 관리 역할만 바뀌고 member 역할은 그대로다
        $this->assertEqualsCanonicalizing([Role::MEMBER, Role::ADMIN], $member->roles()->pluck('slug')->all());
    }

    public function test_super_admin_cannot_remove_own_super_admin_role(): void
    {
        $super = $this->userWithRole(Role::SUPER_ADMIN);
        $this->actingAs($super);

        Livewire::test(ViewUser::class, ['record' => $super->getRouteKey()])
            ->callAction('changeRoles', ['roles' => [Role::ADMIN]])
            ->assertNotified('자기 자신의 최고 관리자 역할은 해제할 수 없습니다.');

        $this->assertTrue($super->fresh()->hasRole(Role::SUPER_ADMIN));
    }

    // ---- 첫 관리자 지정 명령 ----

    public function test_role_command_grants_and_revokes(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('user:role', ['user' => 'OWNER@example.com', 'role' => 'super_admin'])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasRole(Role::SUPER_ADMIN));

        $this->artisan('user:role', ['user' => (string) $user->id, 'role' => 'super_admin', '--remove' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->hasRole(Role::SUPER_ADMIN));
    }

    public function test_role_command_rejects_unknown_role_and_pending_user(): void
    {
        $pending = User::factory()->pending()->create();

        $this->artisan('user:role', ['user' => (string) $pending->id, 'role' => 'member'])->assertFailed();
        $this->artisan('user:role', ['user' => (string) $pending->id, 'role' => 'admin'])->assertFailed();
        $this->artisan('user:role', ['user' => '999999', 'role' => 'admin'])->assertFailed();
        $this->assertSame(0, $pending->roles()->count());
    }
}
