<?php

namespace Tests\Feature;

use App\Enums\LevelChangeReason;
use App\Enums\PointType;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserLevel;
use App\Models\UserLevelHistory;
use App\Services\PointService;
use App\Services\UserLevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 회원 등급 (포인트 정책 §9, ERD §8)
 */
class UserLevelTest extends TestCase
{
    use RefreshDatabase;

    private function level(string $name): UserLevel
    {
        return UserLevel::query()->where('name', $name)->sole();
    }

    /** 누적 포인트만 바꾼 회원 (등급은 그대로 두어 재계산 대상을 만든다) */
    private function userWithLifetime(int $points, UserStatus $status = UserStatus::Active): User
    {
        $user = User::factory()->create(['status' => $status]);
        DB::table('users')->where('id', $user->id)->update(['lifetime_points' => $points, 'point_balance' => $points]);

        return $user->refresh();
    }

    public function test_new_member_starts_at_base_level(): void
    {
        $this->assertSame('브론즈', User::factory()->create()->level?->name);
        $this->assertSame(['브론즈', '실버', '골드', '플래티넘'], UserLevel::query()->orderBy('min_points')->pluck('name')->all());
    }

    public function test_earning_and_revoking_move_level_with_history(): void
    {
        $user = User::factory()->create();
        $points = app(PointService::class);

        $grant = $points->earn($user, PointType::EventReward, 'event_reward:1:u', 1200)->transaction;
        $this->assertSame('실버', $user->refresh()->level?->name);

        $points->earn($user, PointType::EventReward, 'event_reward:2:u', 100);
        $this->assertSame(1, UserLevelHistory::query()->count(), '같은 등급 안에서는 이력이 없다');

        $points->revoke($grant);
        $this->assertSame('브론즈', $user->refresh()->level?->name);
        $this->assertSame(
            [LevelChangeReason::Earn, LevelChangeReason::Revoke],
            UserLevelHistory::query()->orderBy('id')->get()->pluck('reason')->all(),
        );
    }

    public function test_api_shows_level_and_points_to_next_level(): void
    {
        $user = User::factory()->create();
        app(PointService::class)->earn($user, PointType::EventReward, 'event_reward:1:u', 3400);
        Sanctum::actingAs($user->refresh());

        $expected = [
            'level' => ['id' => $this->level('실버')->id, 'name' => '실버', 'min_points' => 1000, 'icon_url' => null],
            'next_level' => ['id' => $this->level('골드')->id, 'name' => '골드', 'min_points' => 4000, 'points_needed' => 600],
        ];
        $this->getJson('/api/v1/points/balance')
            ->assertJsonPath('data.level', $expected['level'])
            ->assertJsonPath('data.next_level', $expected['next_level']);
        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.level.name', '실버')
            ->assertJsonPath('data.next_level.points_needed', 600);

        app(PointService::class)->earn($user, PointType::EventReward, 'event_reward:2:u', 10000);
        Sanctum::actingAs($user->refresh());
        $this->getJson('/api/v1/points/balance')
            ->assertJsonPath('data.level.name', '플래티넘')
            ->assertJsonPath('data.next_level', null);
    }

    public function test_recalculate_all_after_threshold_change(): void
    {
        $moved = $this->userWithLifetime(600);
        $stays = $this->userWithLifetime(300);
        $withdrawn = $this->userWithLifetime(700, UserStatus::Withdrawn);

        $this->level('실버')->update(['min_points' => 500]);
        $changed = app(UserLevelService::class)->recalculateAll();

        $this->assertSame(1, $changed);
        $this->assertSame('실버', $moved->refresh()->level?->name);
        $this->assertSame('브론즈', $stays->refresh()->level?->name);
        $this->assertSame('브론즈', $withdrawn->refresh()->level?->name, '탈퇴 회원은 다시 계산하지 않는다');
        $history = UserLevelHistory::query()->sole();
        $this->assertSame([$moved->id, LevelChangeReason::Admin], [$history->user_id, $history->reason]);
        $this->assertSame(0, app(UserLevelService::class)->recalculateAll(), '두 번째는 바뀌는 회원이 없다');
    }

    public function test_deleting_level_moves_members_down_and_base_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        app(PointService::class)->earn($user, PointType::EventReward, 'event_reward:1:u', 5000);
        $this->assertSame('골드', $user->refresh()->level?->name);

        app(UserLevelService::class)->delete($this->level('골드'));

        $this->assertSame('실버', $user->refresh()->level?->name);
        $this->assertNull(UserLevel::query()->where('name', '골드')->first());
        $this->assertSame(LevelChangeReason::Admin, UserLevelHistory::query()->latest('id')->first()?->reason);

        $this->expectException(ValidationException::class);
        app(UserLevelService::class)->delete($this->level('브론즈'));
    }
}
