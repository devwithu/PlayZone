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
