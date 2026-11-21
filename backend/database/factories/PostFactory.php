<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Board;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 테스트용 게시글. 서비스를 거치지 않으므로 포인트는 바뀌지 않는다. 게시판은 기본 자유게시판.
 *
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_id' => fn () => Board::query()->where('slug', 'free')->value('id'),
            'user_id' => User::factory(),
            'title' => '오늘 해 본 게임 이야기',
            'content' => '주말에 새로 나온 게임을 해 봤는데 생각보다 재미있었어요.',
            'status' => ReviewStatus::Published,
        ];
    }
}
