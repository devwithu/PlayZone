<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureSignupCompleted;
use App\Http\Middleware\EnsureSignupPending;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Apple 로그인은 콜백을 다른 사이트에서 POST(form_post)로 보낸다 (ADR-003)
        $middleware->validateCsrfTokens(except: ['auth/apple/callback']);

        $middleware->alias([
            'signup.completed' => EnsureSignupCompleted::class,
            'signup.pending' => EnsureSignupPending::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // API 기본 요청 제한 (api-spec §6, 한도는 AppServiceProvider의 'api' 리미터)
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // /api/* 오류를 { success: false, code, message, errors } 형식으로 (api-spec §3)
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
