<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// 게임 (W-04, W-05). 로그인 없이 보고 SSR로 검색엔진에 노출된다. 상세 주소는 한글 slug도 쓴다
Route::get('games', [GameController::class, 'index'])->name('games.index');
Route::get('games/{slug}', [GameController::class, 'show'])->where('slug', '[^/]+')->name('games.show');

// 로그인 (Google·Apple 전용, ADR-003)
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::get('auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
});

// Apple은 콜백을 POST(form_post)로 보낸다. CSRF 예외는 bootstrap/app.php
Route::match(['get', 'post'], 'auth/{provider}/callback', [SocialLoginController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('social.callback');

Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// 가입 완료 (pending 회원만)
Route::middleware(['auth', 'signup.pending'])->prefix('signup')->name('signup.')->group(function () {
    Route::get('complete', [SignupController::class, 'show'])->name('show');
    Route::post('complete', [SignupController::class, 'store'])->middleware('throttle:5,1')->name('store');
    Route::get('nickname-check', [SignupController::class, 'checkNickname'])->middleware('throttle:30,1')->name('nickname-check');
});

Route::middleware(['auth', 'signup.completed'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
