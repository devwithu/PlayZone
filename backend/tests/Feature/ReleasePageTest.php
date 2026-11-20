<?php

namespace Tests\Feature;

use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 웹 출시 일정 (화면 설계 W-08)
 */
class ReleasePageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_month_view_with_navigation_and_platform_filter(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-31 16:00:00', 'UTC')); // KST 2027-01-01
        $game = Game::factory()->create();
        foreach (['pc', 'ps5'] as $slug) {
            GameReleaseDate::factory()->for($game)->for(Platform::query()->where('slug', $slug)->sole())->create([
                'release_date' => '2027-01-20', 'date_precision' => ReleaseDatePrecision::Day, 'release_status' => ReleaseStatus::Scheduled,
            ]);
        }

        $this->get('/releases')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('releases/Index')
                ->where('month', '2027-01')
                ->where('prevMonth', '2026-12')
                ->where('nextMonth', '2027-02')
                ->where('today', '2027-01-01')
                ->where('platform', '')
                ->has('entries', 1)
                ->where('entries.0.platforms.1.slug', 'ps5')
                ->has('platforms', 9));

        $this->get('/releases?month=2027-01&platform=switch')
            ->assertInertia(fn (Assert $page) => $page->where('platform', 'switch')->has('entries', 0));

        $this->get('/releases?month=2026-12')
            ->assertInertia(fn (Assert $page) => $page->where('month', '2026-12')->has('entries', 0));
    }

    public function test_invalid_month_and_platform_fall_back(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-05 03:00:00', 'UTC'));

        $this->get('/releases?month=2026-13&platform=pc,ps5')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('month', '2026-12')->where('platform', ''));
    }
}
