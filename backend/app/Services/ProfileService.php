<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * 프로필·수신 동의 변경 (REQ-602, 607, 화면 설계 W-18). 웹 설정 화면과 앱 API가 함께 쓴다.
 *
 * - 프로필 이미지는 public 디스크 `profiles/{user_id}/{uuid}.{ext}`에 두고 상대 경로만 저장한다.
 *   새 이미지로 바꾸거나 지우면, DB 저장이 끝난 뒤에 예전 파일을 지운다.
 * - 닉네임 형식·중복 검사는 요청 검증(ProfileValidationRules)에서 한다. 동시에 같은 닉네임을 저장하면 DB의 UNIQUE 인덱스가 막는다.
 */
class ProfileService
{
    public function update(User $user, string $nickname, ?string $introduction): User
    {
        DB::transaction(function () use ($user, $nickname, $introduction) {
            $user->update(['nickname' => $nickname]);
            $this->profileOf($user)->fill(['introduction' => $introduction])->save();
        });

        return $user->refresh()->load('profile');
    }

    public function updateImage(User $user, UploadedFile $image): User
    {
        $path = $image->storeAs("profiles/{$user->id}", Str::uuid().'.'.($image->extension() ?: 'bin'), ['visibility' => 'public']);
        if ($path === false) {
            throw new RuntimeException('프로필 이미지를 저장하지 못했습니다.');
        }

        try {
            $this->replaceImage($user, $path);
        } catch (Throwable $e) {
            Storage::delete($path);
            throw $e;
        }

        return $user->refresh()->load('profile');
    }

    public function removeImage(User $user): User
    {
        $this->replaceImage($user, null);

        return $user->refresh()->load('profile');
    }

    /**
     * 마케팅 정보 수신 동의 (REQ-607). 동의하면 동의 시각을 남기고, 철회하면 비운다. 이미 같은 상태면 시각을 바꾸지 않는다.
     */
    public function setMarketing(User $user, bool $agreed): User
    {
        if ($agreed !== ($user->marketing_agreed_at !== null)) {
            $user->forceFill(['marketing_agreed_at' => $agreed ? now() : null])->save();
        }

        return $user;
    }

    /**
     * 탈퇴 때 프로필을 지운다 (개인정보). 파일은 커밋 뒤에 지운다.
     */
    public function deleteFor(User $user): void
    {
        $profile = UserProfile::query()->where('user_id', $user->id)->first();
        if ($profile === null) {
            return;
        }

        $path = $profile->profile_image_path;
        $profile->delete();
        if ($path !== null) {
            DB::afterCommit(fn () => Storage::delete($path));
        }
    }

    private function replaceImage(User $user, ?string $path): void
    {
        DB::transaction(function () use ($user, $path) {
            $profile = $this->profileOf($user, lock: true);
            $old = $profile->profile_image_path;
            $profile->fill(['profile_image_path' => $path])->save();

            if ($old !== null && $old !== $path) {
                DB::afterCommit(fn () => Storage::delete($old));
            }
        });
    }

    private function profileOf(User $user, bool $lock = false): UserProfile
    {
        UserProfile::query()->insertOrIgnore(['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);

        return UserProfile::query()->where('user_id', $user->id)->when($lock, fn ($q) => $q->lockForUpdate())->firstOrFail();
    }
}
