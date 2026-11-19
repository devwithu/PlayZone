<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_nickname_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['nickname' => '새닉네임'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('새닉네임', $user->fresh()->nickname);
    }

    public function test_user_can_keep_own_nickname_with_different_case(): void
    {
        $user = User::factory()->create(['nickname' => 'Player']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['nickname' => 'PLAYER'])
            ->assertSessionHasNoErrors();
    }

    public function test_nickname_taken_by_another_user_is_rejected(): void
    {
        User::factory()->create(['nickname' => 'Player']);

        $this->actingAs(User::factory()->create())
            ->patch(route('profile.update'), ['nickname' => 'player'])
            ->assertSessionHasErrors('nickname');
    }
}
