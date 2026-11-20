<?php

namespace Database\Factories;

use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 기본값은 출시된 게임(정확한 날짜). upcoming(), tba()로 바꾼다.
 *
 * @extends Factory<GameReleaseDate>
 */
class GameReleaseDateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'platform_id' => Platform::factory(),
            'release_date' => fake()->dateTimeBetween('-5 years', '-1 day')->format('Y-m-d'),
            'date_precision' => ReleaseDatePrecision::Day,
            'release_status' => ReleaseStatus::Released,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn () => [
            'release_date' => fake()->dateTimeBetween('+1 week', '+1 year')->format('Y-m-d'),
            'date_precision' => ReleaseDatePrecision::Day,
            'release_status' => ReleaseStatus::Scheduled,
        ]);
    }

    public function tba(): static
    {
        return $this->state([
            'release_date' => null,
            'date_precision' => ReleaseDatePrecision::Tba,
            'release_status' => ReleaseStatus::Announced,
        ]);
    }
}
