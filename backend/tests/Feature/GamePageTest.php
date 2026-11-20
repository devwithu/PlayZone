<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameImage;
use App\Models\GameReleaseDate;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 웹 게임 목록·상세 (화면 설계 W-04, W-05)
 */
class GamePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_published_games_with_filters(): void
    {
        $rpg = Game::factory()->create(['title' => '별빛 원정대']);
        $rpg->genres()->attach(Genre::query()->where('slug', 'rpg')->sole());
        Game::factory()->create(['title' => '잿빛 왕국']);
        Game::factory()->draft()->create(['title' => '별빛 비공개']);

        $this->get('/games?q=별빛&genre=rpg&sort=title')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('games/Index')
                ->has('games.items', 1)
                ->where('games.items.0.id', $rpg->id)
                ->where('games.items.0.genres.0.slug', 'rpg')
                ->where('genres.0.slug', 'action')
                ->where('games.pagination.total', 1)
                ->where('filters', [
                    'q' => '별빛', 'genre' => ['rpg'], 'platform' => [], 'release_status' => '', 'sort' => 'title',
                ])
                ->where('keywordError', null)
                ->has('genres', 17)
                ->has('platforms', 9));
    }

    public function test_invalid_query_values_are_ignored_instead_of_failing(): void
    {
        Game::factory()->count(2)->create();

        $this->get('/games?q=별&sort=newest&genre=RPG!&release_status=soon')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('games.items', 2)
                ->where('filters.q', '')
                ->where('filters.genre', [])
                ->where('filters.sort', 'popular')
                ->where('filters.release_status', '')
                ->where('keywordError', '2자 이상 입력해 주세요.'));
    }

    public function test_list_pages_by_24(): void
    {
        Game::factory()->count(25)->create();

        $this->get('/games?page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('games.items', 1)
                ->where('games.pagination', ['current_page' => 2, 'last_page' => 2, 'total' => 25]));
    }

    public function test_detail_page_by_korean_slug_with_meta(): void
    {
        Storage::fake(config('filesystems.default'));
        $game = Game::factory()->create(['title' => '별빛 원정대', 'slug' => '별빛-원정대', 'description' => "별을\n따라가는   모험"]);
        GameImage::factory()->for($game)->cover()->create(['image_path' => 'games/1/cover.webp']);
        GameReleaseDate::factory()->for($game)->for(Platform::query()->where('slug', 'pc')->sole())->create(['release_date' => '2025-01-20']);

        $this->get('/games/'.rawurlencode('별빛-원정대'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('games/Show')
                ->where('game.id', $game->id)
                ->where('game.release_dates.0.display_date', '2025년 1월 20일')
                ->where('game.genres', [])
                ->where('meta.description', '별을 따라가는 모험')
                ->where('meta.image', Storage::url('games/1/cover.webp'))
                ->where('meta.url', route('games.show', '별빛-원정대')));
    }

    public function test_detail_meta_description_falls_back_without_description(): void
    {
        Game::factory()->create(['title' => '잿빛 왕국', 'slug' => 'ashen', 'description' => null]);

        $this->get('/games/ashen')
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.description', '잿빛 왕국 게임 정보, 출시일, 유저 평가')
                ->where('meta.image', null));
    }

    public function test_unpublished_or_missing_game_page_is_not_found(): void
    {
        Game::factory()->draft()->create(['slug' => 'draft-game']);
        Game::factory()->hidden()->create(['slug' => 'hidden-game']);

        $this->get('/games/draft-game')->assertNotFound();
        $this->get('/games/hidden-game')->assertNotFound();
        $this->get('/games/no-such-game')->assertNotFound();
    }

    public function test_logged_in_member_can_browse_games(): void
    {
        $game = Game::factory()->create(['slug' => 'member-view']);

        $this->actingAs(User::factory()->create())
            ->get('/games/member-view')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('game.id', $game->id));
    }
}
