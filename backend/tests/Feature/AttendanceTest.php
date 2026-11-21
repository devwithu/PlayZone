<?php

namespace Tests\Feature;

use App\Enums\PointType;
use App\Models\Attendance;
use App\Models\PointPolicy;
use App\Models\PointTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 출석 (REQ-501~503, 포인트 정책 §3·§5, api-spec §7.6)
 */
class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 2026-11-21 12:00 KST
        $this->travelTo(CarbonImmutable::parse('2026-11-21 03:00:00', 'UTC'));
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** 오늘(KST) 이전 날짜의 출석 기록을 직접 넣는다 */
    private function attendedOn(string $date, int $streak): void
    {
        Attendance::query()->create(['user_id' => $this->user->id, 'attendance_date' => $date, 'streak_days' => $streak]);
    }

    private function ledgerTotal(): int
    {
        return (int) PointTransaction::query()->where('user_id', $this->user->id)->sum('amount');
    }

    public function test_attend_once_a_day(): void
    {
        $this->postJson('/api/v1/attendance')
            ->assertCreated()
            ->assertJsonPath('message', '출석했습니다. 10P를 받았습니다.')
            ->assertJsonPath('data', [
                'attendance_date' => '2026-11-21',
                'streak_days' => 1,
                'point' => ['earned' => 10, 'balance' => 10, 'type' => 'attendance', 'limited' => false],
                'bonuses' => [],
            ]);

        $this->postJson('/api/v1/attendance')
            ->assertStatus(409)
            ->assertJsonPath('code', 'ATTENDANCE_ALREADY_DONE');

        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame(10, $this->user->refresh()->point_balance);
        $this->assertSame(10, $this->ledgerTotal());
        $this->assertSame(
            "attendance:{$this->user->id}:2026-11-21",
            PointTransaction::query()->sole()->idempotency_key,
        );
    }

    public function test_db_rejects_second_row_for_same_day(): void
    {
        $this->attendedOn('2026-11-20', 1);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->attendedOn('2026-11-20', 1);
    }

    public function test_service_day_changes_at_midnight_kst(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 14:59:59', 'UTC')); // 23:59:59 KST
        $this->postJson('/api/v1/attendance')->assertJsonPath('data.attendance_date', '2026-11-21');

        $this->travelTo(CarbonImmutable::parse('2026-11-21 15:00:00', 'UTC')); // 다음 날 00:00 KST
        $this->postJson('/api/v1/attendance')
            ->assertCreated()
            ->assertJsonPath('data.attendance_date', '2026-11-22')
            ->assertJsonPath('data.streak_days', 2);
    }

    public function test_seventh_day_bonus_and_streak_reset_after_missed_day(): void
    {
        $this->attendedOn('2026-11-20', 6);

        $this->postJson('/api/v1/attendance')
            ->assertJsonPath('message', '출석했습니다. 7일 연속 출석 보너스를 받았습니다!')
            ->assertJsonPath('data.streak_days', 7)
            ->assertJsonPath('data.point', ['earned' => 60, 'balance' => 60, 'type' => 'attendance', 'limited' => false])
            ->assertJsonPath('data.bonuses', [['type' => 'attendance_streak_7', 'earned' => 50]]);

        $this->travel(1)->days();
        $this->postJson('/api/v1/attendance')
            ->assertJsonPath('data.streak_days', 8)
            ->assertJsonPath('data.point.earned', 10)
            ->assertJsonPath('data.bonuses', []);

        $this->travel(2)->days(); // 하루 빠짐
        $this->postJson('/api/v1/attendance')->assertJsonPath('data.streak_days', 1);

        $this->assertSame(80, $this->user->refresh()->point_balance);
        $this->assertSame(80, $this->ledgerTotal());
    }

    public function test_thirtieth_day_bonus_then_streak_starts_over(): void
    {
        $this->attendedOn('2026-11-20', 29);

        $this->postJson('/api/v1/attendance')
            ->assertJsonPath('message', '출석했습니다. 30일 연속 출석 보너스를 받았습니다!')
            ->assertJsonPath('data.streak_days', 30)
            ->assertJsonPath('data.point.earned', 310)
            ->assertJsonPath('data.bonuses', [['type' => 'attendance_streak_30', 'earned' => 300]]);

        $this->travel(1)->days();
        $this->postJson('/api/v1/attendance')->assertJsonPath('data.streak_days', 1);
    }

    public function test_today_status_shows_streak_and_next_bonus(): void
    {
        $this->attendedOn('2026-11-20', 5);

        $this->getJson('/api/v1/attendance/today')->assertOk()->assertJsonPath('data', [
            'date' => '2026-11-21',
            'attended' => false,
            'streak_days' => 5,
            'points' => 10,
            'next_bonus' => ['type' => 'attendance_streak_7', 'streak_days' => 7, 'days_left' => 2, 'points' => 50],
        ]);

        $this->postJson('/api/v1/attendance');
        $this->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.attended', true)
            ->assertJsonPath('data.streak_days', 6)
            ->assertJsonPath('data.next_bonus.days_left', 1);

        // 7일째가 지나면 다음 보너스는 30일째
        $this->travel(1)->days();
        $this->postJson('/api/v1/attendance');
        $this->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.next_bonus.streak_days', 30)
            ->assertJsonPath('data.next_bonus.days_left', 23);
    }

    public function test_streak_shown_as_zero_when_yesterday_missed(): void
    {
        $this->attendedOn('2026-11-19', 4);

        $this->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.streak_days', 0)
            ->assertJsonPath('data.next_bonus.days_left', 7);
    }

    public function test_monthly_history(): void
    {
        $this->attendedOn('2026-10-31', 1);
        $this->attendedOn('2026-11-01', 2);
        $this->attendedOn('2026-11-02', 3);
        Attendance::query()->create(['user_id' => User::factory()->create()->id, 'attendance_date' => '2026-11-03', 'streak_days' => 1]);

        $this->getJson('/api/v1/attendance/history')->assertOk()->assertJsonPath('data', [
            'month' => '2026-11',
            'count' => 2,
            'days' => [['date' => '2026-11-01', 'streak_days' => 2], ['date' => '2026-11-02', 'streak_days' => 3]],
        ]);
        $this->getJson('/api/v1/attendance/history?month=2026-10')->assertJsonPath('data.days.0.date', '2026-10-31');
        $this->getJson('/api/v1/attendance/history?month=2026-13')->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->getJson('/api/v1/attendance/history?month=2026-1')->assertUnprocessable();
    }

    public function test_attendance_still_recorded_when_points_disabled(): void
    {
        PointPolicy::query()->where('action', PointType::Attendance->value)->update(['is_active' => false]);

        $this->postJson('/api/v1/attendance')
            ->assertCreated()
            ->assertJsonPath('message', '출석했습니다.')
            ->assertJsonPath('data.point.earned', 0);
        $this->getJson('/api/v1/attendance/today')
            ->assertJsonPath('data.attended', true)
            ->assertJsonPath('data.points', null);
    }

    public function test_requires_completed_signup(): void
    {
        Sanctum::actingAs(User::factory()->pending()->create());
        $this->postJson('/api/v1/attendance')->assertForbidden()->assertJsonPath('code', 'SIGNUP_INCOMPLETE');
        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_web_page_and_check_in(): void
    {
        $this->actingAs($this->user);
        $this->attendedOn('2026-11-20', 2);

        $this->get('/attendance')->assertRedirect('/mypage/attendance');
        $this->get('/mypage/attendance')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('mypage/Attendance')
            ->has('header.point_balance')
            ->where('status.attended', false)
            ->where('status.streak_days', 2)
            ->where('calendar.month', '2026-11')
            ->where('prevMonth', '2026-10')
            ->where('nextMonth', null));

        $this->from('/mypage/attendance')->post('/attendance')
            ->assertRedirect('/mypage/attendance')
            ->assertInertiaFlash('toast.message', '출석했습니다. 10P를 받았습니다.');

        $this->from('/mypage/attendance')->post('/attendance')
            ->assertRedirect('/mypage/attendance')
            ->assertInertiaFlash('toast', ['type' => 'info', 'message' => '오늘은 이미 출석했습니다.']);

        $this->get('/mypage/attendance?month=2026-10')->assertInertia(fn (Assert $page) => $page
            ->where('calendar.month', '2026-10')
            ->where('nextMonth', '2026-11'));
        $this->get('/mypage/attendance?month=bad')->assertInertia(fn (Assert $page) => $page->where('calendar.month', '2026-11'));
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->app['auth']->forgetGuards();
        $this->get('/mypage/attendance')->assertRedirect('/login');
        $this->post('/attendance')->assertRedirect('/login');
    }
}
