<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * 기본값은 가입을 완료한 회원이다.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'nickname' => Str::limit(fake()->unique()->userName(), 12, ''),
            'status' => UserStatus::Active,
            'signup_completed_at' => now(),
            'terms_agreed_at' => now(),
            'age_confirmed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * 소셜 로그인 직후, 가입 완료 전 상태.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'nickname' => null,
            'status' => UserStatus::Pending,
            'signup_completed_at' => null,
            'terms_agreed_at' => null,
            'age_confirmed_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }
}
