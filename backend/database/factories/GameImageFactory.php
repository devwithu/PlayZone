<?php

namespace Database\Factories;

use App\Enums\GameImageType;
use App\Models\Game;
use App\Models\GameImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameImage>
 */
class GameImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'image_path' => 'games/'.fake()->numberBetween(1, 9999).'/'.fake()->uuid().'.webp',
            'image_type' => GameImageType::Screenshot,
            'sort_order' => 0,
        ];
    }

    public function cover(): static
    {
        return $this->state(['image_type' => GameImageType::Cover]);
    }
}
