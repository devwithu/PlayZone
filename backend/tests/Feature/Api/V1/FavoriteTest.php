<?php

namespace Tests\Feature\Api\V1;

use App\Enums\GameStatus;
use App\Models\FavoriteGame;
use App\Models\Game;
use App\Models\User;
use App\Services\FavoriteService;
use App\Services\WithdrawalService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 관심 게임·출시 알림 API (api-spec §7.4, §7.7)
 */
class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    private function favoriteCount(Game $game): int
    {
        return (int) Game::query()->whereKey($game->id)->value('favorite_count');
    }

    public function test_add_and_remove_favorite_keep_count_in_sync_and_are_idempotent(): void
    {
        $game = Game::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/games/{$game->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data', ['is_favorite' => true, 'release_alert' => true, 'favorite_count' => 1]);
        $this->postJson("/api/v1/games/{$game->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.favorite_count', 1);

        $this->deleteJson("/api/v1/games/{$game->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data', ['is_favorite' => false, 'release_alert' => false, 'favorite_count' => 0]);
        $this->deleteJson("/api/v1/games/{$game->id}/favorite")->assertOk();

        $this->assertSame(0, $this->favoriteCount($game));
        $this->assertDatabaseCount('favorite_games', 0);
    }

    public function test_count_reflects_several_members(): void
    {
        $game = Game::factory()->create();
        $service = app(FavoriteService::class);
        [$a, $b] = User::factory()->count(2)->create()->all();

        $service->add($a, $game);
        $service->add($b, $game);
        $service->add($b, $game);
        $this->assertSame(2, $this->favoriteCount($game));

        $service->remove($a, $game);
        $this->assertSame(1, $this->favoriteCount($game));
    }

    public function test_release_alert_on_registers_favorite_and_off_keeps_it(): void
    {
        $game = Game::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/v1/games/{$game->id}/release-alerts")
            ->assertOk()
            ->assertJsonPath('data', ['is_favorite' => true, 'release_alert' => true, 'favorite_count' => 1]);

        $this->deleteJson("/api/v1/games/{$game->id}/release-alerts")
            ->assertOk()
            ->assertJsonPath('data', ['is_favorite' => true, 'release_alert' => false, 'favorite_count' => 1]);

        // 이미 관심 게임이면 알림만 다시 켠다 (수는 그대로)
        $this->postJson("/api/v1/games/{$game->id}/release-alerts")
            ->assertJsonPath('data', ['is_favorite' => true, 'release_alert' => true, 'favorite_count' => 1]);

        // 관심 게임이 아니면 끄기는 아무것도 하지 않는다
        $other = Game::factory()->create();
        $this->deleteJson("/api/v1/games/{$other->id}/release-alerts")
            ->assertJsonPath('data', ['is_favorite' => false, 'release_alert' => false, 'favorite_count' => 0]);
    }

    public function test_only_published_games_can_be_added_but_hidden_ones_can_be_removed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $draft = Game::factory()->draft()->create();

        $this->postJson("/api/v1/games/{$draft->id}/favorite")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->postJson("/api/v1/games/{$draft->id}/release-alerts")->assertNotFound();
        $this->postJson('/api/v1/games/999999/favorite')->assertNotFound();

        $game = Game::factory()->create();
        app(FavoriteService::class)->add($user, $game);
        $game->update(['status' => GameStatus::Hidden]);

        $this->deleteJson("/api/v1/games/{$game->id}/release-alerts")->assertOk()->assertJsonPath('data.release_alert', false);
        $this->deleteJson("/api/v1/games/{$game->id}/favorite")->assertOk()->assertJsonPath('data.is_favorite', false);
    }

    public function test_requires_completed_signup(): void
    {
        $game = Game::factory()->create();

        $this->postJson("/api/v1/games/{$game->id}/favorite")->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');

        Sanctum::actingAs(User::factory()->pending()->create());
        $this->postJson("/api/v1/games/{$game->id}/favorite")->assertForbidden()->assertJsonPath('code', 'SIGNUP_INCOMPLETE');
        $this->getJson('/api/v1/mypage/favorites')->assertForbidden();
    }

    public function test_my_favorites_lists_published_games_newest_first(): void
    {
        $user = User::factory()->create();
        $service = app(FavoriteService::class);
        $first = Game::factory()->create();
        $second = Game::factory()->create();
        $hidden = Game::factory()->create();
        $this->travel(-2)->minutes();
        $service->add($user, $first);
        $this->travelBack();
        $service->add($user, $second);
        $service->add($user, $hidden);
        $service->setReleaseAlert($user, $second, false);
        $hidden->update(['status' => GameStatus::Hidden]);
        $service->add(User::factory()->create(), $first);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/mypage/favorites')
            ->assertOk()
            ->assertJsonPath('data.items.*.game.id', [$second->id, $first->id])
            ->assertJsonPath('data.items.0.release_alert', false)
            ->assertJsonPath('data.items.1.release_alert', true)
            ->assertJsonPath('data.items.0.game.slug', $second->slug)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_game_detail_shows_viewer_state_only_with_token(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();
        app(FavoriteService::class)->add($user, $game);

        $this->getJson("/api/v1/games/{$game->id}")
            ->assertJsonPath('data.is_favorite', null)
            ->assertJsonPath('data.release_alert', null)
            ->assertJsonPath('data.favorite_count', 1);

        $token = $user->createToken('Unity Android')->plainTextToken;
        $this->withToken($token)->getJson("/api/v1/games/{$game->id}")
            ->assertJsonPath('data.is_favorite', true)
            ->assertJsonPath('data.release_alert', true);
    }

    public function test_withdrawal_removes_favorites_and_counts(): void
    {
        $user = User::factory()->create();
        $games = Game::factory()->count(2)->create();
        foreach ($games as $game) {
            app(FavoriteService::class)->add($user, $game);
        }
        app(FavoriteService::class)->add(User::factory()->create(), $games[0]);

        app(WithdrawalService::class)->withdraw($user);

        $this->assertSame(0, FavoriteGame::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, $this->favoriteCount($games[0]));
        $this->assertSame(0, $this->favoriteCount($games[1]));
    }

    public function test_database_rejects_duplicate_favorite(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        app(FavoriteService::class)->add($user, $game);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('favorite_games')->insert(['user_id' => $user->id, 'game_id' => $game->id]);
    }
}
