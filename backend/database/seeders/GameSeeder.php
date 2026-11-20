<?php

namespace Database\Seeders;

use App\Enums\GameStatus;
use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Genre;
use App\Models\Platform;
use Illuminate\Database\Seeder;

/**
 * 로컬 확인용 게임 (가상의 제목). 운영에서는 실행하지 않는다.
 * 출시됨·출시 예정·연기·미정·월/분기 단위 출시일이 골고루 섞이도록 만든다.
 */
class GameSeeder extends Seeder
{
    public function run(): void
    {
        $genres = Genre::query()->pluck('id');
        $platforms = Platform::query()->get();

        foreach (range(1, 30) as $i) {
            $game = Game::factory()->create();
            if ($i > 27) {
                $game->update(['status' => $i === 30 ? GameStatus::Hidden : GameStatus::Draft]);
            }

            $game->genres()->attach($genres->random(fake()->numberBetween(1, 3))->all());

            $onPlatforms = $platforms->random(fake()->numberBetween(1, 4));
            $game->platforms()->attach($onPlatforms->pluck('id')->all());

            foreach ($onPlatforms as $platform) {
                $factory = GameReleaseDate::factory()->for($game)->for($platform);

                match ($i % 6) {
                    0, 1, 2 => $factory->create(),
                    3 => $factory->upcoming()->create(),
                    4 => $factory->create([
                        'release_date' => now()->addMonths(fake()->numberBetween(3, 18))->toDateString(),
                        'date_precision' => fake()->randomElement([ReleaseDatePrecision::Month, ReleaseDatePrecision::Quarter, ReleaseDatePrecision::Year]),
                        'release_status' => fake()->randomElement([ReleaseStatus::Announced, ReleaseStatus::Delayed]),
                    ]),
                    default => $factory->tba()->create(),
                };
            }
        }
    }
}
