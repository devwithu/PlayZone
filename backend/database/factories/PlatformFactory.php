<?php

namespace Database\Factories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 기본 플랫폼은 마이그레이션이 넣으므로, 테스트용 플랫폼은 겹치지 않는 slug를 쓴다.
 *
 * @extends Factory<Platform>
 */
class PlatformFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => '플랫폼 '.fake()->unique()->numberBetween(1, 99999),
            'slug' => 'test-platform-'.fake()->unique()->numberBetween(1, 99999),
            'sort_order' => 1000,
        ];
    }
}
