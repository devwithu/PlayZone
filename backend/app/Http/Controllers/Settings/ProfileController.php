<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LoginProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MarketingConsentRequest;
use App\Http\Requests\Settings\ProfileImageRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 설정 > 프로필 (화면 설계 W-18): 닉네임·소개, 프로필 이미지, 로그인 연결, 마케팅 수신 동의, 탈퇴.
 * 앱 API(Api\V1\MyPageController)와 같은 ProfileService를 쓴다.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $accounts = $user->socialAccounts()->get()->keyBy(fn (SocialAccount $account) => $account->provider->value);
        $user->loadMissing('profile');

        return Inertia::render('settings/Profile', [
            'profile' => [
                'introduction' => $user->profile?->introduction,
                'profile_image_url' => $user->profileImageUrl(),
                'marketing_agreed' => $user->marketing_agreed_at !== null,
                'marketing_agreed_at' => $user->marketing_agreed_at?->toIso8601ZuluString(),
            ],
            'imageMaxMb' => ProfileImageRequest::MAX_KB / 1024,
            // 로그인 연결 (W-18). 이메일은 보내지 않는다
            'socialAccounts' => array_map(fn (LoginProvider $provider) => [
                'id' => $provider->value,
                'label' => $provider->label(),
                'connected' => $accounts->has($provider->value),
            ], LoginProvider::cases()),
            'socialAccountMessage' => $request->session()->get(SocialAccountController::MESSAGE_SESSION_KEY),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $this->profiles->update($this->user($request), $request->nickname(), $request->introduction());

        Inertia::flash('toast', ['type' => 'success', 'message' => '프로필을 저장했습니다.']);

        return to_route('profile.edit');
    }

    public function updateImage(ProfileImageRequest $request): RedirectResponse
    {
        $this->profiles->updateImage($this->user($request), $request->imageFile());
        Inertia::flash('toast', ['type' => 'success', 'message' => '프로필 이미지를 바꿨습니다.']);

        return to_route('profile.edit');
    }

    public function destroyImage(Request $request): RedirectResponse
    {
        $this->profiles->removeImage($this->user($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => '프로필 이미지를 지웠습니다.']);

        return to_route('profile.edit');
    }

    public function updateMarketing(MarketingConsentRequest $request): RedirectResponse
    {
        $this->profiles->setMarketing($this->user($request), $request->agreed());
        Inertia::flash('toast', ['type' => 'success', 'message' => $request->agreed() ? '마케팅 정보 수신에 동의했습니다.' : '마케팅 정보 수신 동의를 철회했습니다.']);

        return to_route('profile.edit');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
