<?php

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
    Route::post('auth/social/{provider}', [SocialLoginController::class, 'store'])
        ->middleware('throttle:social-login')
        ->name('auth.social');
});
