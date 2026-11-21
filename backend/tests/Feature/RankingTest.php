<?php

namespace Tests\Feature;

use App\Enums\PointType;
use App\Enums\RankingType;
use App\Enums\ReviewStatus;
use App\Enums\UserStatus;
use App\Models\GameReview;
use App\Models\PointTransaction;
use App\Models\RankingSnapshot;
use App\Models\User;
use App\Services\RankingService;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 랭킹 (REQ-508, 랭킹 정책, 화면 설계 W-13, api-spec §7.6)
 */
class RankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-11-21(토) 12:00 KST. 이번 주는 11-16(월) ~ 11-22(일)
        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00', 'Asia/Seoul'));
    }

    /**
     * 원장 한 줄을 직접 넣는다 (집계 SQL만 확인하려고 정책 금액·한도를 거치지 않는다)
     */
    private function earn(User $user, PointType $type, int $amount, string $key, ?PointTransaction $revokes = null): PointTransaction
    {
        return PointTransaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'balance_after' => max(0, $amount),
            'type' => $type,
            'reference_type' => $revokes ? 'point_transaction' : null,
            'reference_id' => $revokes?->id,
            'idempotency_key' => $key,
            'service_date' => ServiceDate::today()->toDateString(),
            'created_at' => now(),
        ]);
    }

    public function test_weekly_points_count_activity_minus_revokes_with_ties(): void
    {
        $a = User::factory()->create(['nickname' => '가']);
        $b = User::factory()->create(['nickname' => '나']);
        $c = User::factory()->create(['nickname' => '다']);
        $suspended = User::factory()->create(['status' => UserStatus::Suspended]);

        $this->earn($a, PointType::Attendance, 30, 'a1');
        $this->earn($a, PointType::AdminGrant, 1000, 'a-admin'); // 관리자 지급은 빼고
        $this->earn($b, PointType::ReviewCreate, 30, 'b1');
        $revoked = $this->earn($c, PointType::ReviewCreate, 50, 'c1');
        $this->earn($c, PointType::Revoke, -50, 'revoke-c1', $revoked); // 회수되면 0점이라 순위에 없다
        $this->earn($suspended, PointType::Attendance, 99, 's1');

        // 지난주 적립은 이번 주에 들지 않는다
        $this->travelTo(CarbonImmutable::parse('2026-11-15 23:00', 'Asia/Seoul'));
        $this->earn($c, PointType::Attendance, 10, 'c-last-week');
        $this->travelTo(CarbonImmutable::parse('2026-11-21 12:00', 'Asia/Seoul'));

        $this->assertSame(2, app(RankingService::class)->snapshot(RankingType::PointsWeekly, CarbonImmutable::parse('2026-11-18')));

        $rows = RankingSnapshot::query()->where('snapshot_date', '2026-11-16')->orderBy('user_id')->get(['user_id', 'score', 'rank']);
        $this->assertSame([[$a->id, 30, 1], [$b->id, 30, 1]], $rows->map(fn ($r) => [$r->user_id, $r->score, $r->rank])->all());

        // 다시 집계해도 중복되지 않는다
        app(RankingService::class)->snapshot(RankingType::PointsWeekly, CarbonImmutable::parse('2026-11-16'));
        $this->assertSame(2, RankingSnapshot::query()->where('snapshot_date', '2026-11-16')->count());
    }

    public function test_monthly_reviews_count_published_reviews_in_kst_month(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        GameReview::factory()->for($a)->count(2)->create();
        GameReview::factory()->for($a)->status(ReviewStatus::Hidden)->create();
        GameReview::factory()->for($b)->create();
        // 10-31 23:30 KST = 10월 리뷰
        GameReview::factory()->for($b)->create(['created_at' => CarbonImmutable::parse('2026-10-31 23:30', 'Asia/Seoul')->utc()]);
        // 11-01 00:30 KST(= 10-31 15:30 UTC) = 11월 리뷰
        GameReview::factory()->for($b)->create(['created_at' => CarbonImmutable::parse('2026-11-01 00:30', 'Asia/Seoul')->utc()]);

        app(RankingService::class)->snapshot(RankingType::ReviewsMonthly, CarbonImmutable::parse('2026-11-21'));

        $rows = RankingSnapshot::query()->where('ranking_type', 'reviews_monthly')->orderBy('rank')->orderBy('user_id')->get();
        $this->assertSame([[$a->id, 2, 1], [$b->id, 2, 1]], $rows->map(fn ($r) => [$r->user_id, $r->score, $r->rank])->all());
    }

    public function test_command_snapshots_current_and_previous_on_first_day(): void
    {
        $user = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 20:00', 'Asia/Seoul'));
        $this->earn($user, PointType::Attendance, 10, 'last-week');

        // 월요일 0시 5분: 지난주(11-09)도 다시 집계한다
        $this->travelTo(CarbonImmutable::parse('2026-11-16 00:05', 'Asia/Seoul'));
        $this->artisan('rankings:snapshot')->assertSuccessful();
        $this->assertSame(10, RankingSnapshot::query()->where('ranking_type', 'points_weekly')->where('snapshot_date', '2026-11-09')->value('score'));

        $this->artisan('rankings:snapshot', ['--type' => 'bad'])->assertFailed();
    }

    public function test_web_page_shows_top_and_my_rank(): void
    {
        $users = User::factory()->count(4)->create();
        foreach ($users as $i => $user) {
            $this->earn($user, PointType::Attendance, 10 * ($i + 1), "u{$i}");
        }
        $outside = User::factory()->create();
        app(RankingService::class)->snapshotCurrent();

        $this->get('/rankings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('rankings/Index')
            ->where('ranking.type', 'points_weekly')
            ->where('ranking.period.start', '2026-11-16')
            ->where('ranking.period.is_current', true)
            ->has('ranking.items', 4)
            ->where('ranking.items.0.user.id', $users[3]->id)
            ->where('ranking.items.0.score', 40)
            ->where('ranking.me', null)
            ->where('ranking.next_date', null)
            ->where('ranking.prev_date', null)
            ->has('types', 2));

        $this->actingAs($users[0])->get('/rankings')->assertInertia(fn (Assert $page) => $page->where('ranking.me', ['rank' => 4, 'score' => 10]));
        $this->actingAs($outside)->get('/rankings')->assertInertia(fn (Assert $page) => $page->where('ranking.me', ['rank' => null, 'score' => 0]));

        // 잘못된 값과 미래 날짜는 기본값으로
        $this->get('/rankings?type=bad&date=2026-13-01')->assertInertia(fn (Assert $page) => $page->where('ranking.type', 'points_weekly')->where('ranking.period.start', '2026-11-16'));
        $this->get('/rankings?date=2027-01-01')->assertInertia(fn (Assert $page) => $page->where('ranking.period.start', '2026-11-16'));

        // 지난 기간: 집계가 없으면 빈 목록, 다음 기간 링크는 있다
        $this->get('/rankings?type=reviews_monthly&date=2026-10-05')->assertInertia(fn (Assert $page) => $page
            ->where('ranking.period.label', '2026년 10월')
            ->has('ranking.items', 0)
            ->where('ranking.updated_at', null)
            ->where('ranking.next_date', '2026-11-01'));
    }

    public function test_api(): void
    {
        $user = User::factory()->create();
        $this->earn($user, PointType::Attendance, 10, 'u');
        app(RankingService::class)->snapshotCurrent();

        $this->getJson('/api/v1/rankings')->assertOk()
            ->assertJsonPath('data.items.0.rank', 1)
            ->assertJsonPath('data.items.0.user.id', $user->id)
            ->assertJsonPath('data.me', null);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/rankings?type=points_weekly&date=2026-11-17')->assertOk()->assertJsonPath('data.me.rank', 1);

        $this->getJson('/api/v1/rankings?type=daily')->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
        $this->getJson('/api/v1/rankings?date=21-11-2026')->assertUnprocessable();
    }
}
