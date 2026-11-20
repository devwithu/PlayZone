<?php

namespace Tests\Feature;

use App\Enums\PointType;
use App\Exceptions\ApiException;
use App\Models\PointPolicy;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\PointService;
use App\Services\WithdrawalService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * 포인트 원장 (포인트 정책 §2~§7)
 */
class PointServiceTest extends TestCase
{
    use RefreshDatabase;

    private PointService $points;

    protected function setUp(): void
    {
        parent::setUp();
        $this->points = app(PointService::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** 잔액·누적은 원장과 항상 맞아야 한다 (§2-2) */
    private function assertLedgerMatches(User $user): void
    {
        $user->refresh();
        $ledger = PointTransaction::query()->where('user_id', $user->id);

        $this->assertSame((int) (clone $ledger)->sum('amount'), $user->point_balance, '잔액 = 원장 합계');
        $last = (clone $ledger)->orderByDesc('id')->first();
        $this->assertSame($last->balance_after ?? 0, $user->point_balance, '마지막 balance_after = 잔액');
    }

    public function test_earn_uses_policy_amount_and_is_idempotent_per_key(): void
    {
        $user = User::factory()->create();

        $first = $this->points->earn($user, PointType::Signup, "signup:{$user->id}");
        $again = $this->points->earn($user, PointType::Signup, "signup:{$user->id}");

        $this->assertSame(['earned' => 100, 'balance' => 100, 'type' => 'signup', 'limited' => false], $first->toArray());
        $this->assertSame(0, $again->earned);
        $this->assertTrue($again->alreadyProcessed);
        $this->assertSame(100, $user->refresh()->lifetime_points);
        $this->assertSame(1, PointTransaction::query()->count());
        $this->assertLedgerMatches($user);
    }

    public function test_only_active_members_earn_and_inactive_policy_pays_nothing(): void
    {
        $pending = User::factory()->pending()->create();
        $this->assertSame(0, $this->points->earn($pending, PointType::Attendance, 'attendance:x')->earned);

        PointPolicy::query()->where('action', 'attendance')->update(['is_active' => false]);
        $user = User::factory()->create();
        $this->assertSame(0, $this->points->earn($user, PointType::Attendance, "attendance:{$user->id}:d")->earned);
        $this->assertSame(0, PointTransaction::query()->count());
    }

    public function test_daily_count_limit_resets_at_kst_midnight(): void
    {
        $user = User::factory()->create();
        // KST 2026-12-01 23:50 (UTC 14:50)
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-01 14:50:00', 'UTC'));

        foreach (range(1, 3) as $i) {
            $this->assertSame(100, $this->points->earn($user, PointType::ReviewCreate, "review_create:{$user->id}:{$i}")->earned);
        }
        $limited = $this->points->earn($user, PointType::ReviewCreate, "review_create:{$user->id}:4");
        $this->assertSame([0, true], [$limited->earned, $limited->limited]);

        // KST 2026-12-02 00:10 → 새 날
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-01 15:10:00', 'UTC'));
        $this->assertSame(100, $this->points->earn($user, PointType::ReviewCreate, "review_create:{$user->id}:4")->earned);
        $this->assertSame('2026-12-02', PointTransaction::query()->latest('id')->first()?->service_date->toDateString());
        $this->assertLedgerMatches($user);
    }

    public function test_daily_point_limit_pays_only_the_remainder(): void
    {
        PointPolicy::query()->where('action', 'review_like_received')->update(['daily_limit_points' => 12]);
        $user = User::factory()->create();

        $earned = collect(range(1, 4))->map(fn ($i) => $this->points->earn($user, PointType::ReviewLikeReceived, "review_like_received:1:{$i}"));

        $this->assertSame([5, 5, 2, 0], $earned->map->earned->all());
        $this->assertSame([false, false, true, true], $earned->map->limited->all());
        $this->assertLedgerMatches($user);
    }

    public function test_event_reward_and_admin_grant_need_explicit_amount(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();

        $this->assertSame(500, $this->points->earn($user, PointType::EventReward, "event_reward:1:{$user->id}", 500, 'event', 1)->earned);
        $grant = $this->points->earn($user, PointType::AdminGrant, 'admin:abc', 30, description: '오류 보상', admin: $admin);
        $this->assertSame($admin->id, $grant->transaction?->admin_id);
        $this->assertSame('오류 보상', $grant->transaction?->description);

        $this->expectException(InvalidArgumentException::class);
        $this->points->earn($user, PointType::EventReward, 'event_reward:2:x');
    }

    public function test_spend_checks_balance_and_does_not_reduce_lifetime_points(): void
    {
        $user = User::factory()->create();
        $this->points->earn($user, PointType::EventReward, 'event_reward:1:u', 100);

        $spent = $this->points->spend($user, PointType::EventEntry, 70, 'event_entry:2:u', 'event', 2);
        $this->assertSame([-70, 30], [$spent->earned, $spent->balance]);
        $this->assertTrue($this->points->spend($user, PointType::EventEntry, 70, 'event_entry:2:u')->alreadyProcessed);
        $this->assertSame(100, $user->refresh()->lifetime_points);

        try {
            $this->points->spend($user, PointType::EventEntry, 31, 'event_entry:3:u');
            $this->fail('잔액보다 많이 쓸 수 없어야 한다');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(30, $user->refresh()->point_balance);
        $this->assertLedgerMatches($user);
    }

    public function test_revoke_takes_back_up_to_balance_and_reduces_lifetime_points(): void
    {
        $user = User::factory()->create();
        $grant = $this->points->earn($user, PointType::EventReward, 'event_reward:1:u', 100)->transaction;
        $this->points->spend($user, PointType::EventEntry, 60, 'event_entry:1:u');

        $revoked = $this->points->revoke($grant, '부정 응모');

        $this->assertSame([-40, 0], [$revoked->earned, $revoked->balance]);
        $this->assertSame('부정 응모 (잔액 부족으로 60P 미회수)', $revoked->transaction?->description);
        $this->assertSame('point_transaction', $revoked->transaction?->reference_type);
        $this->assertSame($grant->id, $revoked->transaction?->reference_id);
        $this->assertSame(60, $user->refresh()->lifetime_points);
        $this->assertTrue($this->points->revoke($grant)->alreadyProcessed);
        $this->assertLedgerMatches($user);

        $this->expectException(InvalidArgumentException::class);
        $this->points->revoke($revoked->transaction);
    }

    public function test_withdrawal_expires_remaining_balance_and_keeps_ledger(): void
    {
        $user = User::factory()->create();
        $this->points->earn($user, PointType::Signup, "signup:{$user->id}");

        app(WithdrawalService::class)->withdraw($user);

        $user->refresh();
        $this->assertSame(0, $user->point_balance);
        $this->assertSame(100, $user->lifetime_points);
        $this->assertSame(['signup', 'withdraw_expire'], PointTransaction::query()->orderBy('id')->pluck('type')->map->value->all());
        $this->assertLedgerMatches($user);
    }

    public function test_ledger_rows_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $transaction = $this->points->earn($user, PointType::Signup, "signup:{$user->id}")->transaction;

        try {
            $transaction->update(['amount' => 999]);
            $this->fail('원장은 수정할 수 없어야 한다');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $transaction->delete();
    }

    public function test_database_constraints_protect_the_ledger(): void
    {
        $user = User::factory()->create();
        $this->points->earn($user, PointType::Signup, "signup:{$user->id}");
        $row = fn (array $override) => [
            'user_id' => $user->id, 'amount' => 10, 'balance_after' => 10, 'type' => 'admin_grant',
            'idempotency_key' => 'admin:'.uniqid(), 'service_date' => '2026-12-01', 'created_at' => now(), ...$override,
        ];

        foreach ([
            'duplicate key' => fn () => DB::table('point_transactions')->insert($row(['idempotency_key' => "signup:{$user->id}"])),
            'zero amount' => fn () => DB::table('point_transactions')->insert($row(['amount' => 0])),
            'negative balance_after' => fn () => DB::table('point_transactions')->insert($row(['balance_after' => -1])),
            'negative user balance' => fn () => DB::table('users')->whereKey($user->id)->update(['point_balance' => -1]),
        ] as $case => $statement) {
            try {
                DB::transaction($statement);
                $this->fail("{$case}: DB가 막아야 한다");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
