<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LoginProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $accounts = $user->socialAccounts()->get()->keyBy(fn (SocialAccount $account) => $account->provider->value);

        return Inertia::render('settings/Profile', [
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
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => '프로필을 저장했습니다.']);

        return to_route('profile.edit');
    }
}
