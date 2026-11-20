<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 기본 장르는 마이그레이션이 넣으므로, 테스트용 장르는 겹치지 않는 slug를 쓴다.
 *
 * @extends Factory<Genre>
 */
class GenreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '장르 '.fake()->unique()->numberBetween(1, 99999),
            'slug' => 'test-genre-'.fake()->unique()->numberBetween(1, 99999),
            'sort_order' => 1000,
        ];
    }
}
