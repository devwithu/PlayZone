<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\Auth\AccountController;
use App\Http\Controllers\Api\V1\Auth\SocialAccountController;
use App\Http\Controllers\Api\V1\Auth\SocialLoginController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\MyPageController;
use App\Http\Controllers\Api\V1\PointController;
use App\Http\Controllers\Api\V1\ReleaseController;
use App\Http\Controllers\Api\V1\ReviewCommentController;
use App\Http\Controllers\Api\V1\ReviewController;
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

        // O: 가입 완료 회원만
        Route::middleware(['auth:sanctum', 'signup.completed'])->group(function () {
            Route::post('social-accounts/{provider}', [SocialAccountController::class, 'store'])
                ->middleware('throttle:social-login')
                ->name('social-accounts.store');
            Route::delete('social-accounts/{provider}', [SocialAccountController::class, 'destroy'])
                ->name('social-accounts.destroy');
        });
    });

    // 게임 (§7.2) -: 로그인 없이 호출
    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/{game}', [GameController::class, 'show'])->whereNumber('game')->name('games.show');
    Route::get('genres', [GameController::class, 'genres'])->name('genres.index');
    Route::get('platforms', [GameController::class, 'platforms'])->name('platforms.index');
    Route::get('rating-categories', [GameController::class, 'ratingCategories'])->name('rating-categories.index');
    Route::get('games/{game}/rating-summary', [GameController::class, 'ratingSummary'])->whereNumber('game')->name('games.rating-summary');

    // 리뷰 (§7.3) -: 목록·상세·댓글은 로그인 없이. 토큰을 보내면 is_mine, is_liked를 채운다
    Route::get('games/{game}/reviews', [ReviewController::class, 'index'])->whereNumber('game')->name('games.reviews.index');
    Route::get('reviews/{review}', [ReviewController::class, 'show'])->whereNumber('review')->name('reviews.show');
    Route::get('reviews/{review}/comments', [ReviewCommentController::class, 'index'])->whereNumber('review')->name('reviews.comments.index');

    // 리뷰 쓰기 O: 가입 완료 회원만
    Route::middleware(['auth:sanctum', 'signup.completed'])->group(function () {
        Route::post('games/{game}/reviews', [ReviewController::class, 'store'])->whereNumber('game')->middleware('throttle:10,1')->name('games.reviews.store');
        Route::put('reviews/{review}', [ReviewController::class, 'update'])->whereNumber('review')->middleware('throttle:20,1')->name('reviews.update');
        Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->whereNumber('review')->name('reviews.destroy');
        Route::post('reviews/{review}/like', [ReviewController::class, 'like'])->whereNumber('review')->name('reviews.like');
        Route::delete('reviews/{review}/like', [ReviewController::class, 'unlike'])->whereNumber('review')->name('reviews.unlike');
        Route::post('reviews/{review}/comments', [ReviewCommentController::class, 'store'])->whereNumber('review')->middleware('throttle:20,1')->name('reviews.comments.store');
        Route::put('review-comments/{comment}', [ReviewCommentController::class, 'update'])->whereNumber('comment')->name('review-comments.update');
        Route::delete('review-comments/{comment}', [ReviewCommentController::class, 'destroy'])->whereNumber('comment')->name('review-comments.destroy');
    });

    // 출시 일정 (§7.4) -: 로그인 없이 호출.
    Route::get('releases', [ReleaseController::class, 'index'])->name('releases.index');
    Route::get('releases/upcoming', [ReleaseController::class, 'upcoming'])->name('releases.upcoming');
    Route::get('games/{game}/releases', [ReleaseController::class, 'game'])->whereNumber('game')->name('games.releases');

    // 마이페이지·관심 게임·출시 알림 (§7.4, §7.7) O: 가입 완료 회원만
    Route::middleware(['auth:sanctum', 'signup.completed'])->group(function () {
        Route::get('mypage', [MyPageController::class, 'index'])->name('mypage.index');
        Route::get('mypage/reviews', [MyPageController::class, 'reviews'])->name('mypage.reviews');
        Route::get('mypage/favorites', [FavoriteController::class, 'index'])->name('mypage.favorites');
        Route::post('games/{game}/favorite', [FavoriteController::class, 'store'])->whereNumber('game')->name('games.favorite.store');
        Route::delete('games/{game}/favorite', [FavoriteController::class, 'destroy'])->whereNumber('game')->name('games.favorite.destroy');
        Route::post('games/{game}/release-alerts', [FavoriteController::class, 'enableReleaseAlert'])->whereNumber('game')->name('games.release-alerts.store');
        Route::delete('games/{game}/release-alerts', [FavoriteController::class, 'disableReleaseAlert'])->whereNumber('game')->name('games.release-alerts.destroy');
    });

    // 출석 (§7.6) O: 가입 완료 회원만. 출석은 회원당 1분에 10번까지
    Route::middleware(['auth:sanctum', 'signup.completed'])->prefix('attendance')->name('attendance.')->group(function () {
        Route::get('today', [AttendanceController::class, 'today'])->name('today');
        Route::post('/', [AttendanceController::class, 'store'])->middleware('throttle:10,1')->name('store');
        Route::get('history', [AttendanceController::class, 'history'])->name('history');
    });

    // 포인트 (§7.6) O: 가입 완료 회원만
    Route::middleware(['auth:sanctum', 'signup.completed'])->prefix('points')->name('points.')->group(function () {
        Route::get('balance', [PointController::class, 'balance'])->name('balance');
        Route::get('history', [PointController::class, 'history'])->name('history');
    });
});
