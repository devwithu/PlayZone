<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameImage;
use App\Models\GameReleaseDate;
use App\Models\Genre;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 게임 API (api-spec §7.2)
 */
class GameTest extends TestCase
{
    use RefreshDatabase;

    private function genre(string $slug): Genre
    {
        return Genre::query()->where('slug', $slug)->sole();
    }

    private function platform(string $slug): Platform
    {
        return Platform::query()->where('slug', $slug)->sole();
    }

    private function released(Game $game, string $platform, string $date): void
    {
        GameReleaseDate::factory()->for($game)->for($this->platform($platform))->create(['release_date' => $date]);
    }

    /**
     * @return list<int>
     */
    private function ids(string $query = ''): array
    {
        return $this->getJson('/api/v1/games'.$query)->assertOk()->json('data.items.*.id');
    }

    // ---- 목록 ----

    public function test_list_shows_only_published_games_with_card_fields(): void
    {
        Storage::fake(config('filesystems.default'));
        $game = Game::factory()->create(['title' => '별빛 원정대', 'slug' => '별빛-원정대']);
        $game->genres()->attach($this->genre('rpg'));
        $this->released($game, 'ps5', '2025-03-10');
        $this->released($game, 'pc', '2025-01-20');
        GameImage::factory()->for($game)->cover()->create(['image_path' => "games/{$game->id}/cover.webp"]);
        Game::factory()->draft()->create();
        Game::factory()->hidden()->create();

        $this->getJson('/api/v1/games')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.slug', '별빛-원정대')
            ->assertJsonPath('data.items.0.cover_url', Storage::url("games/{$game->id}/cover.webp"))
            ->assertJsonPath('data.items.0.genres.0.slug', 'rpg')
            ->assertJsonPath('data.items.0.platforms.0.slug', 'pc')
            ->assertJsonPath('data.items.0.is_reviewable', true)
            ->assertJsonPath('data.items.0.rating_avg', null)
            // 대표 출시일: 출시된 플랫폼 중 가장 이른 날
            ->assertJsonPath('data.items.0.primary_release.release_date', '2025-01-20')
            ->assertJsonPath('data.items.0.primary_release.display_date', '2025년 1월 20일');
    }

    public function test_search_by_title_matches_middle_and_escapes_wildcards(): void
    {
        $match = Game::factory()->create(['title' => '별빛 원정대']);
        Game::factory()->create(['title' => '잿빛 왕국']);
        $percent = Game::factory()->create(['title' => '100% 오렌지']);

        $this->assertSame([$match->id], $this->ids('?q=원정'));
        $this->assertSame([$percent->id], $this->ids('?q='.urlencode('0%')));
        $this->assertSame([], $this->ids('?q='.urlencode('_빛')));
    }

    public function test_search_keyword_needs_two_characters(): void
    {
        $this->getJson('/api/v1/games?q=별')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('errors.q.0', '검색어은(는) 2자 이상이어야 합니다.');
    }

    public function test_filter_by_genre_and_platform_slugs(): void
    {
        $rpg = Game::factory()->create();
        $rpg->genres()->attach($this->genre('rpg'));
        $rpg->platforms()->attach($this->platform('switch'));
        $action = Game::factory()->create();
        $action->genres()->attach($this->genre('action'));
        $action->platforms()->attach($this->platform('ps5'));
        $puzzle = Game::factory()->create();
        $puzzle->genres()->attach($this->genre('puzzle'));

        $this->assertEqualsCanonicalizing([$rpg->id, $action->id], $this->ids('?genre=rpg,action'));
        $this->assertSame([$action->id], $this->ids('?genre=rpg,action&platform=ps5'));
        $this->assertSame([], $this->ids('?genre=no-such-genre'));

        $this->getJson('/api/v1/games?genre=RPG')->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    public function test_filter_by_release_status(): void
    {
        $released = Game::factory()->create();
        $this->released($released, 'pc', '2025-01-01');
        GameReleaseDate::factory()->for($released)->for($this->platform('ps5'))->upcoming()->create();

        $upcoming = Game::factory()->create();
        GameReleaseDate::factory()->for($upcoming)->for($this->platform('pc'))->upcoming()->create();

        $tba = Game::factory()->create();
        GameReleaseDate::factory()->for($tba)->for($this->platform('pc'))->tba()->create();

        $cancelled = Game::factory()->create();
        GameReleaseDate::factory()->for($cancelled)->for($this->platform('pc'))->upcoming()->create(['release_status' => ReleaseStatus::Cancelled]);

        $this->assertSame([$released->id], $this->ids('?release_status=released'));
        $this->assertEqualsCanonicalizing([$upcoming->id, $tba->id], $this->ids('?release_status=upcoming'));
    }

    public function test_sort_options(): void
    {
        $a = Game::factory()->create(['title' => '가나다']);
        $b = Game::factory()->create(['title' => '나다라']);
        $c = Game::factory()->create(['title' => '다라마']);
        Game::query()->whereKey($a->id)->update(['review_count' => 5, 'rating_avg' => 7.5]);
        Game::query()->whereKey($b->id)->update(['review_count' => 9, 'rating_avg' => null]);
        Game::query()->whereKey($c->id)->update(['review_count' => 1, 'rating_avg' => 9.0]);
        $this->released($a, 'pc', '2024-06-01');
        $this->released($c, 'pc', '2025-06-01');

        $this->assertSame([$b->id, $a->id, $c->id], $this->ids(), '기본: 리뷰 많은 순');
        $this->assertSame([$c->id, $a->id, $b->id], $this->ids('?sort=rating'), '평균 없는 게임은 뒤로');
        $this->assertSame([$c->id, $a->id, $b->id], $this->ids('?sort=latest_release'), '출시 안 된 게임은 뒤로');
        $this->assertSame([$a->id, $b->id, $c->id], $this->ids('?sort=title'));

        $this->getJson('/api/v1/games?sort=newest')->assertUnprocessable();
    }

    public function test_pagination_keeps_query_in_links_and_limits_per_page(): void
    {
        Game::factory()->count(3)->create();

        $this->getJson('/api/v1/games?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination', ['current_page' => 2, 'last_page' => 2, 'per_page' => 2, 'total' => 3]);

        $this->getJson('/api/v1/games?per_page=51')->assertUnprocessable();
    }

    // ---- 상세 ----

    public function test_detail_returns_images_and_release_dates_in_order(): void
    {
        Storage::fake(config('filesystems.default'));
        $game = Game::factory()->create(['description' => '별을 따라가는 모험', 'developer' => '스튜디오 별']);
        GameImage::factory()->for($game)->cover()->create(['image_path' => 'games/1/cover.webp']);
        GameImage::factory()->for($game)->create(['image_path' => 'games/1/s2.webp', 'sort_order' => 2]);
        GameImage::factory()->for($game)->create(['image_path' => 'games/1/s1.webp', 'sort_order' => 1]);
        GameReleaseDate::factory()->for($game)->for($this->platform('switch-2'))->tba()->create();
        GameReleaseDate::factory()->for($game)->for($this->platform('ps5'))->create([
            'release_date' => '2027-05-20', 'date_precision' => ReleaseDatePrecision::Quarter, 'release_status' => ReleaseStatus::Scheduled,
        ]);
        $this->released($game, 'pc', '2026-03-01');

        $this->getJson("/api/v1/games/{$game->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $game->id)
            ->assertJsonPath('data.description', '별을 따라가는 모험')
            ->assertJsonPath('data.cover_url', Storage::url('games/1/cover.webp'))
            ->assertJsonPath('data.screenshot_urls', [Storage::url('games/1/s1.webp'), Storage::url('games/1/s2.webp')])
            ->assertJsonPath('data.banner_url', null)
            ->assertJsonPath('data.is_reviewable', true)
            ->assertJsonPath('data.release_dates.0.platform.slug', 'pc')
            ->assertJsonPath('data.release_dates.0.release_status', 'released')
            ->assertJsonPath('data.release_dates.1.platform.slug', 'ps5')
            ->assertJsonPath('data.release_dates.1.release_date', '2027-04-01')
            ->assertJsonPath('data.release_dates.1.date_precision', 'quarter')
            ->assertJsonPath('data.release_dates.1.display_date', '2027년 2분기')
            ->assertJsonPath('data.release_dates.2.platform.slug', 'switch-2')
            ->assertJsonPath('data.release_dates.2.release_date', null)
            ->assertJsonPath('data.release_dates.2.display_date', '미정');
    }

    public function test_unpublished_or_missing_game_is_not_found(): void
    {
        $draft = Game::factory()->draft()->create();
        $hidden = Game::factory()->hidden()->create();

        foreach ([$draft->id, $hidden->id, 999999] as $id) {
            $this->getJson("/api/v1/games/{$id}")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        }
        $this->getJson('/api/v1/games/abc')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    }

    // ---- 장르·플랫폼 ----

    public function test_genre_and_platform_lists_follow_admin_order(): void
    {
        $this->getJson('/api/v1/genres')
            ->assertOk()
            ->assertJsonCount(17, 'data')
            ->assertJsonPath('data.0', ['id' => $this->genre('action')->id, 'name' => '액션', 'slug' => 'action']);

        $this->getJson('/api/v1/platforms')
            ->assertOk()
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('data.0.slug', 'pc');
    }
}
