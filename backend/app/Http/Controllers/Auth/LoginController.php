<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * 로그인 화면 (W-02).
     */
    public function show(Request $request): Response
    {
        // 비회원이 관심 게임 같은 버튼을 누르면 ?redirect=/games/... 로 온다. 로그인 후 그 화면으로 돌려보낸다.
        // 다른 사이트로 보내지 않도록 이 사이트의 경로(/로 시작, // 나 \ 없음)만 받는다
        $redirect = $request->query('redirect');
        if (is_string($redirect) && preg_match('#^/(?![/\\\\])[^\\\\]*$#', $redirect) === 1) {
            redirect()->setIntendedUrl(url($redirect));
        }

        return Inertia::render('auth/Login', [
            'providers' => array_map(
                fn (LoginProvider $provider) => ['id' => $provider->value, 'label' => $provider->label()],
                LoginProvider::cases(),
            ),
            'error' => $request->session()->get('loginError'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
