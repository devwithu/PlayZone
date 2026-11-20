<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameImage;
use App\Models\GameReleaseDate;
use App\Models\Genre;
use App\Models\Platform;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 게임 정보 스키마 (ERD §4): DB 제약, 출시일 보정·표시, 리뷰 가능 여부, 기본 데이터, 제목 검색
 */
class GameSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PostgreSQL은 제약 위반 후 트랜잭션 전체가 중단되므로 저장점(중첩 트랜잭션) 안에서 실행한다.
     */
    private function assertViolates(string $constraint, callable $statement): void
    {
        try {
            DB::transaction($statement);
        } catch (QueryException $e) {
            $this->assertStringContainsString($constraint, $e->getMessage());

            return;
        }

        $this->fail("{$constraint} 제약이 위반되지 않았습니다.");
    }

    // ---- DB 제약 ----

    public function test_game_constraints(): void
    {
        $game = Game::factory()->create(['slug' => 'star-expedition']);

        $this->assertViolates('games_status_check', fn () => DB::table('games')->where('id', $game->id)->update(['status' => 'deleted']));
        $this->assertViolates('games_rating_avg_check', fn () => DB::table('games')->where('id', $game->id)->update(['rating_avg' => 0.5]));
        $this->assertViolates('games_counts_check', fn () => DB::table('games')->where('id', $game->id)->update(['review_count' => -1]));
        $this->assertViolates('games_slug_unique', fn () => Game::factory()->create(['slug' => 'star-expedition']));
    }

    public function test_game_has_only_one_cover(): void
    {
        $game = Game::factory()->create();
        GameImage::factory()->for($game)->cover()->create();
        GameImage::factory()->for($game)->count(2)->create();

        $this->assertViolates('game_images_one_cover_unique', fn () => GameImage::factory()->for($game)->cover()->create());
        $this->assertViolates('game_images_image_type_check', fn () => DB::table('game_images')->insert([
            'game_id' => $game->id, 'image_path' => 'games/x.webp', 'image_type' => 'icon', 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    public function test_release_date_constraints(): void
    {
        $game = Game::factory()->create();
        $platform = Platform::query()->where('slug', 'ps5')->sole();
        $row = fn (array $values) => DB::table('game_release_dates')->insert([
            'game_id' => $game->id, 'platform_id' => $platform->id, 'created_at' => now(), 'updated_at' => now(), ...$values,
        ]);

        $this->assertViolates('game_release_dates_tba_check', fn () => $row(['release_date' => '2027-01-01', 'date_precision' => 'tba', 'release_status' => 'announced']));
        $this->assertViolates('game_release_dates_tba_check', fn () => $row(['release_date' => null, 'date_precision' => 'month', 'release_status' => 'announced']));
        $this->assertViolates('game_release_dates_released_check', fn () => $row(['release_date' => null, 'date_precision' => 'tba', 'release_status' => 'released']));
        $this->assertViolates('game_release_dates_release_status_check', fn () => $row(['release_date' => '2027-01-01', 'date_precision' => 'day', 'release_status' => 'early_access']));

        GameReleaseDate::factory()->for($game)->for($platform)->create();
        $this->assertViolates('game_release_dates_game_id_platform_id_unique', fn () => GameReleaseDate::factory()->for($game)->for($platform)->upcoming()->create());
    }

    // ---- 출시일 보정·표시 ----

    public function test_release_date_is_normalized_to_start_of_period_and_displayed(): void
    {
        $cases = [
            [ReleaseDatePrecision::Day, '2027-05-20', '2027-05-20', '2027년 5월 20일'],
            [ReleaseDatePrecision::Month, '2027-05-20', '2027-05-01', '2027년 5월'],
            [ReleaseDatePrecision::Quarter, '2027-05-20', '2027-04-01', '2027년 2분기'],
            [ReleaseDatePrecision::Year, '2027-05-20', '2027-01-01', '2027년'],
            [ReleaseDatePrecision::Tba, '2027-05-20', null, '미정'],
        ];

        foreach ($cases as [$precision, $input, $stored, $display]) {
            $releaseDate = GameReleaseDate::factory()->create([
                'release_date' => $input,
                'date_precision' => $precision,
                'release_status' => ReleaseStatus::Announced,
            ])->fresh();

            $this->assertSame($stored, $releaseDate?->release_date?->toDateString(), $precision->value);
            $this->assertSame($display, $releaseDate?->displayDate(), $precision->value);
        }
    }

    // ---- 리뷰 가능 여부 (리뷰 정책 §2) ----

    public function test_game_is_reviewable_when_released_on_any_platform(): void
    {
        $upcoming = Game::factory()->create();
        GameReleaseDate::factory()->for($upcoming)->upcoming()->create();
        GameReleaseDate::factory()->for($upcoming)->tba()->create();

        $partly = Game::factory()->create();
        GameReleaseDate::factory()->for($partly)->upcoming()->create();
        GameReleaseDate::factory()->for($partly)->create();

        $this->assertFalse($upcoming->isReviewable());
        $this->assertTrue($partly->isReviewable());
        $this->assertSame([$partly->id], Game::query()->reviewable()->pluck('id')->all());
    }

    public function test_published_scope_hides_draft_and_hidden_games(): void
    {
        $published = Game::factory()->create();
        Game::factory()->draft()->create();
        Game::factory()->hidden()->create();

        $this->assertSame([$published->id], Game::query()->published()->pluck('id')->all());
        $this->assertSame(GameStatus::Draft, (new Game)->status);
    }

    // ---- 관계·이미지 ----

    public function test_relations_are_ordered_and_image_url_uses_storage(): void
    {
        $game = Game::factory()->create();
        $game->genres()->attach(Genre::query()->whereIn('slug', ['rpg', 'action'])->pluck('id'));
        $game->platforms()->attach(Platform::query()->whereIn('slug', ['ios', 'pc'])->pluck('id'));
        $cover = GameImage::factory()->for($game)->cover()->create(['image_path' => "games/{$game->id}/cover.webp"]);

        $this->assertSame(['action', 'rpg'], $game->genres->pluck('slug')->all());
        $this->assertSame(['pc', 'ios'], $game->platforms->pluck('slug')->all());
        $this->assertTrue($game->cover?->is($cover));
        $this->assertSame(Storage::url("games/{$game->id}/cover.webp"), $cover->url());
        $this->assertStringNotContainsString('http', $cover->image_path);
    }

    // ---- 기본 데이터·검색 ----

    public function test_default_genres_and_platforms_exist(): void
    {
        $this->assertSame(17, Genre::query()->count());
        $this->assertSame(9, Platform::query()->count());
        $this->assertSame('RPG', Genre::query()->where('slug', 'rpg')->value('name'));
        $this->assertSame('pc', Platform::query()->ordered()->value('slug'));
    }

    public function test_title_search_uses_trigram_index(): void
    {
        $this->assertTrue(DB::table('pg_indexes')->where('indexname', 'games_title_trgm_index')->exists());

        $match = Game::factory()->create(['title' => '별빛 원정대']);
        Game::factory()->create(['title' => '잿빛 왕국']);

        // 기본 검색: 중간 일치 (ERD §12-5)
        $this->assertSame([$match->id], Game::query()->where('title', 'ilike', '%원정%')->pluck('id')->all());
        // 오타 추천: word_similarity
        $this->assertSame([$match->id], Game::query()->whereRaw('? <% title', ['별빛 원정데'])->pluck('id')->all());
    }

    public function test_local_seeder_creates_sample_games(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(30, Game::query()->count());
        $this->assertSame(27, Game::query()->published()->count());
        $this->assertTrue(Game::query()->reviewable()->exists());
        $this->assertTrue(GameReleaseDate::query()->where('date_precision', ReleaseDatePrecision::Tba)->exists());
    }
}
