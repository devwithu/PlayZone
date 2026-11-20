<?php

use App\Http\Controllers\Api\V1\Auth\AccountController;
use App\Http\Controllers\Api\V1\Auth\SocialLoginController;
use Illuminate\Support\Facades\Route;

/*
 * Unity 앱용 REST API (api-spec). 주소는 /api/v1/..., 라우트 이름은 api.v1.*
 * 응답은 App\Http\Responses\ApiResponse, 오류는 App\Exceptions\ApiException으로 만든다.
 *
 * 인증 표기(api-spec §7): -는 인증 없음, P는 auth:sanctum, O는 auth:sanctum + signup.completed
 */
Route::prefix('v1')->name('api.v1.')->group(function () {
    // 인증·계정 (§7.1)
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('social/{provider}', [SocialLoginController::class, 'store'])
            ->middleware('throttle:social-login')
            ->name('social');

        // P: 가입 미완료 회원도 호출 가능
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AccountController::class, 'me'])->name('me');
            Route::delete('me', [AccountController::class, 'destroy'])->name('withdraw');
            Route::post('signup/complete', [AccountController::class, 'completeSignup'])
                ->middleware('throttle:signup-complete')
                ->name('signup.complete');
            Route::get('nickname-check', [AccountController::class, 'checkNickname'])->name('nickname-check');
            Route::post('logout', [AccountController::class, 'logout'])->name('logout');
        });
    });
});
