<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Game;
use App\Models\GameReview;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 테스트용 리뷰. 서비스를 거치지 않으므로 포인트·게임 평균은 바뀌지 않는다.
 * 집계가 필요하면 GameRatingService::recalculate()를 부른다.
 *
 * @extends Factory<GameReview>
 */
class GameReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'user_id' => User::factory(),
            'platform_id' => Platform::factory(),
            'title' => '재미있게 플레이했습니다',
            'content' => str_repeat('전투와 탐험이 모두 즐거운 게임이었습니다. ', 3),
            'overall_score' => fake()->numberBetween(1, 10),
            'status' => ReviewStatus::Published,
        ];
    }

    public function status(ReviewStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
