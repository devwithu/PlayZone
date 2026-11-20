<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    private const TITLE_FIRST = ['별빛', '잿빛', '푸른', '붉은', '잊힌', '마지막', '끝없는', '고요한', '부서진', '황금빛', '심연의', '새벽의'];

    private const TITLE_SECOND = ['원정대', '왕국', '연대기', '항해', '전설', '요새', '탐험가', '수호자', '방랑자', '도시', '섬', '기사단'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement(self::TITLE_FIRST).' '.fake()->randomElement(self::TITLE_SECOND);

        return [
            'title' => $title,
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->paragraph(),
            'developer' => fake()->company(),
            'publisher' => fake()->optional()->company(),
            'status' => GameStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => GameStatus::Draft]);
    }

    public function hidden(): static
    {
        return $this->state(['status' => GameStatus::Hidden]);
    }
}
