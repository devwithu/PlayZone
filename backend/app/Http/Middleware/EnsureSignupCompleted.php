<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 로그인한 회원 중 가입을 마친 활성 회원만 통과시킨다 (API 명세 §2의 SIGNUP_INCOMPLETE와 같은 규칙).
 */
class EnsureSignupCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->status === UserStatus::Pending) {
            if ($request->isMethod('GET')) {
                redirect()->setIntendedUrl($request->fullUrl());
            }

            return to_route('signup.show');
        }

        if ($user->status !== UserStatus::Active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->with('loginError', '이용할 수 없는 계정입니다.');
        }

        return $next($request);
    }
}
