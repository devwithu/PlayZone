<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 출시 일정 API (api-spec §7.4)
 */
class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // KST 2026-12-10 오전 (UTC로는 전날 밤이어도 KST 날짜 기준이어야 한다)
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-09 23:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function release(Game $game, string $platform, ?string $date, ReleaseDatePrecision $precision = ReleaseDatePrecision::Day, ReleaseStatus $status = ReleaseStatus::Scheduled): void
    {
        GameReleaseDate::factory()->for($game)->for(Platform::query()->where('slug', $platform)->sole())->create([
            'release_date' => $date, 'date_precision' => $precision, 'release_status' => $status,
        ]);
    }

    public function test_schedule_defaults_to_this_month_in_kst_and_groups_platforms(): void
    {
        $a = Game::factory()->create(['title' => '가 게임']);
        $this->release($a, 'pc', '2026-12-03', status: ReleaseStatus::Released);
        $this->release($a, 'ps5', '2026-12-03', status: ReleaseStatus::Released);
        $this->release($a, 'switch', '2027-02-01');
        $b = Game::factory()->create(['title' => '나 게임']);
        $this->release($b, 'pc', '2026-12-20', ReleaseDatePrecision::Month); // 12월 중
        $c = Game::factory()->create(['title' => '다 게임']);
        $this->release($c, 'pc', '2026-11-30');
        $this->release($c, 'ps5', '2027-01-01');

        $this->getJson('/api/v1/releases')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.game.id', $a->id)
            ->assertJsonPath('data.0.release_date', '2026-12-03')
            ->assertJsonPath('data.0.platforms.*.slug', ['pc', 'ps5'])
            ->assertJsonPath('data.0.release_status', 'released')
            ->assertJsonPath('data.0.display_date', '2026년 12월 3일')
            // 월 단위는 그달 날짜별 출시 뒤
            ->assertJsonPath('data.1.game.id', $b->id)
            ->assertJsonPath('data.1.release_date', '2026-12-01')
            ->assertJsonPath('data.1.date_precision', 'month')
            ->assertJsonPath('data.1.display_date', '2026년 12월');
    }

    public function test_schedule_includes_quarter_and_year_periods_that_overlap(): void
    {
        $quarter = Game::factory()->create();
        $this->release($quarter, 'pc', '2027-02-15', ReleaseDatePrecision::Quarter); // 2027-01-01 ~ 03-31
        $year = Game::factory()->create();
        $this->release($year, 'pc', '2027-06-01', ReleaseDatePrecision::Year); // 2027-01-01 ~ 12-31
        $tba = Game::factory()->create();
        $this->release($tba, 'pc', null, ReleaseDatePrecision::Tba, ReleaseStatus::Announced);

        $ids = fn (string $q) => $this->getJson('/api/v1/releases'.$q)->assertOk()->json('data.*.game.id');

        $this->assertSame([$quarter->id, $year->id], $ids('?from=2027-03-01&to=2027-03-31'), '분기가 연보다 앞');
        $this->assertSame([$year->id], $ids('?from=2027-04-01&to=2027-04-30'));
        $this->assertSame([], $ids('?from=2026-12-01&to=2026-12-31'));
    }

    public function test_schedule_hides_cancelled_and_unpublished_and_filters_platform(): void
    {
        $game = Game::factory()->create();
        $this->release($game, 'pc', '2026-12-15');
        $this->release($game, 'ps5', '2026-12-15');
        $this->release(Game::factory()->create(), 'pc', '2026-12-16', status: ReleaseStatus::Cancelled);
        $this->release(Game::factory()->draft()->create(), 'pc', '2026-12-17');

        $this->getJson('/api/v1/releases')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/releases?platform=ps5')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.platforms.*.slug', ['ps5']);
        $this->getJson('/api/v1/releases?platform=switch')->assertJsonCount(0, 'data');
    }

    public function test_schedule_validates_range(): void
    {
        $this->getJson('/api/v1/releases?from=2026-12-01')
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED')->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/releases?from=2026-12-10&to=2026-12-01')->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/releases?from=2026-12-1&to=2026-12-31')->assertJsonValidationErrors('from');
        $this->getJson('/api/v1/releases?from=2026-01-01&to=2026-04-04') // 양 끝 포함 94일
            ->assertJsonPath('errors.to.0', '기간은 93일 이내로 지정해 주세요.');
        $this->getJson('/api/v1/releases?from=2026-01-01&to=2026-04-03')->assertOk(); // 93일
    }

    public function test_upcoming_lists_unreleased_from_today_in_order_with_tba_last(): void
    {
        $today = Game::factory()->create(['title' => '오늘']);
        $this->release($today, 'pc', '2026-12-10');
        $month = Game::factory()->create(['title' => '이번 달']);
        $this->release($month, 'pc', '2026-12-01', ReleaseDatePrecision::Month); // 기간이 아직 안 끝남
        $later = Game::factory()->create(['title' => '내년']);
        $this->release($later, 'pc', '2027-03-01');
        $tba = Game::factory()->create(['title' => '미정']);
        $this->release($tba, 'pc', null, ReleaseDatePrecision::Tba, ReleaseStatus::Announced);
        // 제외: 어제 예정이던 것, 이미 출시, 취소
        $this->release(Game::factory()->create(), 'pc', '2026-12-09');
        $this->release(Game::factory()->create(), 'pc', '2026-12-20', status: ReleaseStatus::Released);
        $this->release(Game::factory()->create(), 'pc', '2026-12-21', status: ReleaseStatus::Cancelled);

        $this->getJson('/api/v1/releases/upcoming')
            ->assertOk()
            ->assertJsonPath('data.items.*.game.id', [$today->id, $month->id, $later->id, $tba->id])
            ->assertJsonPath('data.items.3.display_date', '미정')
            ->assertJsonPath('data.pagination.total', 4);

        $this->getJson('/api/v1/releases/upcoming?per_page=2&page=2')
            ->assertJsonPath('data.items.*.game.id', [$later->id, $tba->id])
            ->assertJsonPath('data.pagination.last_page', 2);
    }

    public function test_game_releases_lists_every_platform(): void
    {
        $game = Game::factory()->create();
        $this->release($game, 'switch', null, ReleaseDatePrecision::Tba, ReleaseStatus::Announced);
        $this->release($game, 'pc', '2026-01-05', status: ReleaseStatus::Released);
        $this->release($game, 'ps5', '2027-01-05', status: ReleaseStatus::Cancelled);

        $this->getJson("/api/v1/games/{$game->id}/releases")
            ->assertOk()
            ->assertJsonPath('data.*.platform.slug', ['pc', 'ps5', 'switch'])
            ->assertJsonPath('data.1.release_status', 'cancelled');

        $draft = Game::factory()->draft()->create();
        $this->getJson("/api/v1/games/{$draft->id}/releases")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    }
}
