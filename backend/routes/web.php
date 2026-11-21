<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SignupController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\ReviewCommentController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// 게임 (W-04, W-05). 로그인 없이 보고 SSR로 검색엔진에 노출된다. 상세 주소는 한글 slug도 쓴다
Route::get('games', [GameController::class, 'index'])->name('games.index');
Route::get('games/{slug}', [GameController::class, 'show'])->where('slug', '[^/]+')->name('games.show');

// 출시 일정 (W-08)
Route::get('releases', [ReleaseController::class, 'index'])->name('releases.index');

// 커뮤니티 (W-09~W-11) 공개. 1차는 웹 전용 (MOB-008)
Route::get('community', [CommunityController::class, 'index'])->name('community.index');
Route::get('community/{board}', [CommunityController::class, 'board'])->where('board', '[a-z0-9-]+')->name('community.board');
Route::get('posts/{post}', [CommunityController::class, 'show'])->whereNumber('post')->name('posts.show');

// 리뷰 상세 (W-06) 공개. 검토 중·숨김 리뷰는 작성자만
Route::get('reviews/{review}', [ReviewController::class, 'show'])->whereNumber('review')->name('reviews.show');

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
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');

    // 게시글 쓰기 (W-12)·추천·댓글
    Route::get('community/{board}/write', [CommunityController::class, 'create'])->where('board', '[a-z0-9-]+')->name('community.write');
    Route::post('posts', [CommunityController::class, 'store'])->middleware('throttle:10,1')->name('posts.store');
    Route::get('posts/{post}/edit', [CommunityController::class, 'edit'])->whereNumber('post')->name('posts.edit');
    Route::put('posts/{post}', [CommunityController::class, 'update'])->whereNumber('post')->middleware('throttle:20,1')->name('posts.update');
    Route::delete('posts/{post}', [CommunityController::class, 'destroy'])->whereNumber('post')->name('posts.destroy');
    Route::post('posts/{post}/like', [CommunityController::class, 'like'])->whereNumber('post')->name('posts.like.store');
    Route::delete('posts/{post}/like', [CommunityController::class, 'unlike'])->whereNumber('post')->name('posts.like.destroy');
    Route::post('posts/{post}/comments', [CommunityController::class, 'storeComment'])->whereNumber('post')->middleware('throttle:20,1')->name('posts.comments.store');
    Route::put('post-comments/{comment}', [CommunityController::class, 'updateComment'])->whereNumber('comment')->name('post-comments.update');
    Route::delete('post-comments/{comment}', [CommunityController::class, 'destroyComment'])->whereNumber('comment')->name('post-comments.destroy');

    // 리뷰 쓰기 (W-07)·추천·댓글
    Route::get('games/{slug}/reviews/create', [ReviewController::class, 'create'])->where('slug', '[^/]+')->name('games.reviews.create');
    Route::post('games/{game}/reviews', [ReviewController::class, 'store'])->whereNumber('game')->middleware('throttle:10,1')->name('games.reviews.store');
    Route::get('reviews/{review}/edit', [ReviewController::class, 'edit'])->whereNumber('review')->name('reviews.edit');
    Route::put('reviews/{review}', [ReviewController::class, 'update'])->whereNumber('review')->middleware('throttle:20,1')->name('reviews.update');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->whereNumber('review')->name('reviews.destroy');
    Route::post('reviews/{review}/like', [ReviewController::class, 'like'])->whereNumber('review')->name('reviews.like.store');
    Route::delete('reviews/{review}/like', [ReviewController::class, 'unlike'])->whereNumber('review')->name('reviews.like.destroy');
    Route::post('reviews/{review}/comments', [ReviewCommentController::class, 'store'])->whereNumber('review')->middleware('throttle:20,1')->name('reviews.comments.store');
    Route::put('review-comments/{comment}', [ReviewCommentController::class, 'update'])->whereNumber('comment')->name('review-comments.update');
    Route::delete('review-comments/{comment}', [ReviewCommentController::class, 'destroy'])->whereNumber('comment')->name('review-comments.destroy');
    Route::post('attendance', [AttendanceController::class, 'store'])->middleware('throttle:10,1')->name('attendance.store');
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // 관심 게임·출시 알림 (REQ-604, REQ-204)
    Route::get('mypage/favorites', [FavoriteController::class, 'index'])->name('mypage.favorites');
    Route::post('games/{game}/favorite', [FavoriteController::class, 'store'])->whereNumber('game')->name('games.favorite.store');
    Route::delete('games/{game}/favorite', [FavoriteController::class, 'destroy'])->whereNumber('game')->name('games.favorite.destroy');
    Route::post('games/{game}/release-alert', [FavoriteController::class, 'enableReleaseAlert'])->whereNumber('game')->name('games.release-alert.store');
    Route::delete('games/{game}/release-alert', [FavoriteController::class, 'disableReleaseAlert'])->whereNumber('game')->name('games.release-alert.destroy');
});

require __DIR__.'/settings.php';
