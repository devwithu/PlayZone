<?php

namespace Database\Factories;

use App\Enums\LoginProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => LoginProvider::Google,
            'provider_user_id' => (string) fake()->unique()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'last_login_at' => now(),
        ];
    }
}
