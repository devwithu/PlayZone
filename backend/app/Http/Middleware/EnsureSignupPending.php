<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 가입 완료 화면은 가입 미완료(pending) 회원만 볼 수 있다.
 */
class EnsureSignupPending
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isPending()) {
            return to_route('home');
        }

        return $next($request);
    }
}
