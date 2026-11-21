<?php

namespace Tests\Feature\Admin;

use App\Enums\PointType;
use App\Filament\Resources\PointPolicies\Pages\ManagePointPolicies;
use App\Filament\Resources\UserLevels\Pages\ManageUserLevels;
use App\Models\PointPolicy;
use App\Models\Role;
use App\Models\User;
use App\Models\UserLevel;
use App\Services\PointService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 관리자 회원 등급·포인트 정책 (포인트 정책 §3, §9)
 */
class PointAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->value('id'));

        return $user;
    }

    private function level(string $name): UserLevel
    {
        return UserLevel::query()->where('name', $name)->sole();
    }

    public function test_only_admins_open_point_settings(): void
    {
        $this->actingAs($this->userWithRole(Role::MODERATOR));
        $this->get('/admin/user-levels')->assertForbidden();
        $this->get('/admin/point-policies')->assertForbidden();

        $this->actingAs($this->userWithRole(Role::ADMIN));
        $this->get('/admin/user-levels')->assertOk()->assertSee('플래티넘');
        $this->get('/admin/point-policies')->assertOk()->assertSee('리뷰 추천받음');
    }

    public function test_changing_threshold_recalculates_member_levels(): void
    {
        $member = User::factory()->create();
        DB::table('users')->where('id', $member->id)->update(['lifetime_points' => 700]);
        $this->actingAs($this->userWithRole(Role::ADMIN));

        Livewire::test(ManageUserLevels::class)
            ->callTableAction('edit', $this->level('실버'), data: ['name' => '실버', 'min_points' => 500])
            ->assertHasNoTableActionErrors();

        $this->assertSame(500, $this->level('실버')->min_points);
        $this->assertSame('실버', $member->refresh()->level?->name);
    }

    public function test_adding_and_deleting_levels(): void
    {
        $member = User::factory()->create();
        app(PointService::class)->earn($member, PointType::EventReward, 'event_reward:1:m', 2500);
        $this->actingAs($this->userWithRole(Role::ADMIN));
        $page = fn () => Livewire::test(ManageUserLevels::class);

        $page()->callAction('create', data: ['name' => '실버 플러스', 'min_points' => 2000])->assertHasNoActionErrors();
        $this->assertSame('실버 플러스', $member->refresh()->level?->name);

        $page()->callAction('create', data: ['name' => '중복', 'min_points' => 2000])->assertHasActionErrors(['min_points' => 'unique']);
        $page()->callAction('create', data: ['name' => '실버', 'min_points' => 3000])->assertHasActionErrors(['name' => 'unique']);
        $page()->callAction('create', data: ['name' => '두번째 기본', 'min_points' => 0])->assertHasActionErrors(['min_points']);

        $page()->callTableAction('delete', $this->level('실버 플러스'));
        $this->assertSame('실버', $member->refresh()->level?->name);
    }

    public function test_base_level_keeps_zero_and_cannot_be_deleted(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMIN));
        $base = $this->level('브론즈');

        Livewire::test(ManageUserLevels::class)
            ->assertTableActionHidden('delete', $base)
            ->assertTableActionVisible('delete', $this->level('실버'))
            ->callTableAction('edit', $base, data: ['name' => '새싹', 'min_points' => 50])
            ->assertHasNoTableActionErrors();

        $base->refresh();
        $this->assertSame(['새싹', 0], [$base->name, $base->min_points]);
    }

    public function test_policy_change_applies_to_next_grant_and_policies_cannot_be_added(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMIN));
        $attendance = PointPolicy::query()->where('action', 'attendance')->sole();

        Livewire::test(ManagePointPolicies::class)
            ->assertActionDoesNotExist('create')
            ->callTableAction('edit', $attendance, data: ['points' => 15, 'daily_limit_count' => 1, 'daily_limit_points' => null, 'is_active' => true])
            ->assertHasNoTableActionErrors();

        $member = User::factory()->create();
        $this->assertSame(15, app(PointService::class)->earn($member, PointType::Attendance, "attendance:{$member->id}:d")->earned);

        Livewire::test(ManagePointPolicies::class)
            ->callTableAction('edit', $attendance, data: ['points' => 0])
            ->assertHasTableActionErrors(['points' => 'min']);
    }
}
