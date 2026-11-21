<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 설정 > 프로필·알림 (REQ-602, 607, 화면 설계 W-18, api-spec §7.7)
 */
class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
        $this->user = User::factory()->create(['nickname' => '게임평가왕']);
    }

    public function test_settings_page_shows_profile_and_marketing(): void
    {
        $this->actingAs($this->user)->get('/settings/profile')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/Profile')
            ->where('profile', ['introduction' => null, 'profile_image_url' => null, 'marketing_agreed' => false, 'marketing_agreed_at' => null])
            ->where('imageMaxMb', 5)
            ->where('auth.user.profile_image_url', null));
    }

    public function test_nickname_and_introduction(): void
    {
        $this->actingAs($this->user);

        $this->patch('/settings/profile', ['nickname' => '새닉네임', 'introduction' => "  RPG를 좋아해요\n오픈월드 최고  "])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile')
            ->assertInertiaFlash('toast.message', '프로필을 저장했습니다.');
        $this->assertSame('새닉네임', $this->user->refresh()->nickname);
        $this->assertSame("RPG를 좋아해요\n오픈월드 최고", $this->user->profile->introduction);

        // 비우면 지운다
        $this->patch('/settings/profile', ['nickname' => '새닉네임', 'introduction' => '   ']);
        $this->assertNull($this->user->profile()->value('introduction'));

        $this->patch('/settings/profile', ['nickname' => '새닉네임', 'introduction' => str_repeat('가', 301)])
            ->assertSessionHasErrors('introduction');
        $this->assertSame(1, UserProfile::query()->count());
    }

    public function test_profile_image_upload_replace_and_remove(): void
    {
        $this->actingAs($this->user);

        $this->post('/settings/profile/image', ['image' => UploadedFile::fake()->image('a.png', 200, 200)])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');
        $first = $this->user->profile()->value('profile_image_path');
        $this->assertStringStartsWith("profiles/{$this->user->id}/", $first);
        Storage::assertExists($first);

        $this->get('/mypage')->assertInertia(fn (Assert $page) => $page
            ->where('header.profile_image_url', Storage::url($first))
            ->where('auth.user.profile_image_url', Storage::url($first)));

        // 바꾸면 예전 파일은 지운다
        $this->post('/settings/profile/image', ['image' => UploadedFile::fake()->image('b.webp', 300, 300)]);
        $second = $this->user->profile()->value('profile_image_path');
        $this->assertNotSame($first, $second);
        Storage::assertMissing($first);
        Storage::assertExists($second);

        $this->delete('/settings/profile/image')->assertRedirect('/settings/profile');
        $this->assertNull($this->user->profile()->value('profile_image_path'));
        Storage::assertMissing($second);
    }

    public function test_profile_image_validation(): void
    {
        $this->actingAs($this->user);

        $this->post('/settings/profile/image', ['image' => UploadedFile::fake()->image('a.gif', 200, 200)])
            ->assertSessionHasErrors(['image' => 'jpg, png, webp 이미지만 올릴 수 있습니다.']);
        $this->post('/settings/profile/image', ['image' => UploadedFile::fake()->image('small.png', 50, 50)])
            ->assertSessionHasErrors(['image' => '가로·세로 100px 이상인 이미지를 올려 주세요.']);
        $this->post('/settings/profile/image', ['image' => UploadedFile::fake()->image('big.png', 200, 200)->size(5 * 1024 + 1)])
            ->assertSessionHasErrors(['image' => '프로필 이미지는 5MB 이하여야 합니다.']);
        $this->post('/settings/profile/image', [])->assertSessionHasErrors('image');
        $this->assertSame(0, UserProfile::query()->count());
    }

    public function test_marketing_consent(): void
    {
        $this->actingAs($this->user);

        $this->put('/settings/marketing', ['agreed' => true])->assertInertiaFlash('toast.message', '마케팅 정보 수신에 동의했습니다.');
        $agreedAt = $this->user->refresh()->marketing_agreed_at;
        $this->assertNotNull($agreedAt);

        // 같은 상태로 다시 보내면 동의 시각을 바꾸지 않는다
        $this->travel(1)->hours();
        $this->put('/settings/marketing', ['agreed' => true]);
        $this->assertEquals($agreedAt, $this->user->refresh()->marketing_agreed_at);

        $this->put('/settings/marketing', ['agreed' => false])->assertInertiaFlash('toast.message', '마케팅 정보 수신 동의를 철회했습니다.');
        $this->assertNull($this->user->refresh()->marketing_agreed_at);

        $this->put('/settings/marketing', [])->assertSessionHasErrors('agreed');
    }

    public function test_withdrawal_deletes_profile_and_image(): void
    {
        $this->actingAs($this->user)->post('/settings/profile/image', ['image' => UploadedFile::fake()->image('a.png', 200, 200)]);
        $this->patch('/settings/profile', ['nickname' => '게임평가왕', 'introduction' => '소개']);
        $path = $this->user->profile()->value('profile_image_path');

        app(WithdrawalService::class)->withdraw($this->user);

        $this->assertSame(0, UserProfile::query()->count());
        Storage::assertMissing($path);
    }

    public function test_api_profile_image_and_marketing(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/v1/mypage/profile', ['nickname' => '앱닉네임', 'introduction' => '앱에서 소개'])->assertOk()
            ->assertJsonPath('data.nickname', '앱닉네임')
            ->assertJsonPath('data.introduction', '앱에서 소개')
            ->assertJsonPath('data.profile_image_url', null);

        User::factory()->create(['nickname' => '다른회원']);
        $this->putJson('/api/v1/mypage/profile', ['nickname' => '다른회원'])->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonValidationErrors('nickname');

        $response = $this->postJson('/api/v1/mypage/profile/image', ['image' => UploadedFile::fake()->image('a.jpg', 200, 200)])->assertOk();
        $this->assertStringContainsString("/profiles/{$this->user->id}/", (string) $response->json('data.profile_image_url'));

        $this->deleteJson('/api/v1/mypage/profile/image')->assertOk()->assertJsonPath('data.profile_image_url', null);
        $this->deleteJson('/api/v1/mypage/profile/image')->assertOk();

        $this->putJson('/api/v1/mypage/marketing', ['agreed' => true])->assertOk()->assertJsonPath('data.marketing_agreed', true);
        $this->putJson('/api/v1/mypage/marketing', ['agreed' => false])->assertOk()->assertJsonPath('data.marketing_agreed', false);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.introduction', '앱에서 소개');
    }

    public function test_guest_and_pending_cannot_change_profile(): void
    {
        $this->post('/settings/profile/image', ['image' => UploadedFile::fake()->image('a.png', 200, 200)])->assertRedirect('/login');
        $this->put('/settings/marketing', ['agreed' => true])->assertRedirect('/login');

        Sanctum::actingAs(User::factory()->pending()->create());
        $this->putJson('/api/v1/mypage/profile', ['nickname' => '대기회원'])->assertForbidden();
    }
}
