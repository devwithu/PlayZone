<?php

namespace Tests\Feature;

use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use App\Models\User;
use App\Services\FavoriteService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 웹 관심 게임·출시 알림 (REQ-604, REQ-204)
 */
class FavoritePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_toggles_favorite_and_alert_from_game_page(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['slug' => 'star']);

        $this->actingAs($user)->from('/games/star')
            ->post("/games/{$game->id}/favorite")
            ->assertRedirect('/games/star');

        $this->get('/games/star')->assertInertia(fn (Assert $page) => $page
            ->where('game.is_favorite', true)
            ->where('game.release_alert', true)
            ->where('game.favorite_count', 1));

        $this->from('/games/star')->delete("/games/{$game->id}/release-alert")->assertRedirect('/games/star');
        $this->get('/games/star')->assertInertia(fn (Assert $page) => $page
            ->where('game.is_favorite', true)
            ->where('game.release_alert', false));

        $this->from('/games/star')->delete("/games/{$game->id}/favorite");
        $this->get('/games/star')->assertInertia(fn (Assert $page) => $page
            ->where('game.is_favorite', false)
            ->where('game.favorite_count', 0));
    }

    public function test_guest_sees_null_state_and_cannot_toggle(): void
    {
        $game = Game::factory()->create(['slug' => 'star']);

        $this->get('/games/star')->assertInertia(fn (Assert $page) => $page
            ->where('game.is_favorite', null)
            ->where('game.release_alert', null));

        $this->post("/games/{$game->id}/favorite")->assertRedirect('/login');
        $this->assertDatabaseCount('favorite_games', 0);
    }

    public function test_pending_member_is_sent_to_signup(): void
    {
        $game = Game::factory()->create();

        $this->actingAs(User::factory()->pending()->create())
            ->post("/games/{$game->id}/favorite")
            ->assertRedirect(route('signup.show'));
    }

    public function test_login_page_remembers_safe_redirect_path_only(): void
    {
        $this->get('/login?redirect=/games/star')->assertOk()->assertSessionHas('url.intended', url('/games/star'));

        foreach (['//evil.example', '/\\evil.example', 'https://evil.example', 'games/star'] as $unsafe) {
            $this->flushSession();
            $this->get('/login?redirect='.urlencode($unsafe))->assertOk()->assertSessionMissing('url.intended');
        }
    }

    public function test_my_favorites_page(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['title' => '별빛 원정대']);
        app(FavoriteService::class)->add($user, $game);

        $this->actingAs($user)->get('/mypage/favorites')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('mypage/Favorites')
                ->has('favorites.items', 1)
                ->where('favorites.items.0.game.title', '별빛 원정대')
                ->where('favorites.items.0.release_alert', true)
                ->where('favorites.pagination.total', 1));

        auth()->logout();
        $this->get('/mypage/favorites')->assertRedirect('/login');
    }

    public function test_release_schedule_shows_my_alerts(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-01 03:00:00', 'UTC'));
        $user = User::factory()->create();
        [$on, $off] = Game::factory()->count(2)->create()->all();
        foreach ([$on, $off] as $game) {
            GameReleaseDate::factory()->for($game)->for(Platform::query()->where('slug', 'pc')->sole())->create([
                'release_date' => '2027-01-20', 'date_precision' => ReleaseDatePrecision::Day, 'release_status' => ReleaseStatus::Scheduled,
            ]);
        }
        app(FavoriteService::class)->add($user, $on);

        $this->get('/releases')->assertInertia(fn (Assert $page) => $page->where('alerts', null));

        $this->actingAs($user)->get('/releases')
            ->assertInertia(fn (Assert $page) => $page
                ->where("alerts.{$on->id}", true)
                ->where("alerts.{$off->id}", false));

        CarbonImmutable::setTestNow();
    }
}
