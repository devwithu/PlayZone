<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SocialAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'signup.completed'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/image', [ProfileController::class, 'updateImage'])->middleware('throttle:10,1')->name('profile.image.update');
    Route::delete('settings/profile/image', [ProfileController::class, 'destroyImage'])->name('profile.image.destroy');
    Route::put('settings/marketing', [ProfileController::class, 'updateMarketing'])->name('profile.marketing.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    // 로그인 연결 (REQ-008). 연결은 제공자 화면을 거쳐 auth/{provider}/callback으로 돌아온다
    Route::get('settings/social-accounts/{provider}/link', [SocialAccountController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('social-accounts.link');
    Route::delete('settings/social-accounts/{provider}', [SocialAccountController::class, 'destroy'])
        ->name('social-accounts.destroy');
});

// 회원 탈퇴: 가입 미완료 회원도 할 수 있다 (ADR-003 §4)
Route::delete('settings/account', [AccountController::class, 'destroy'])
    ->middleware(['auth', 'throttle:5,1'])
    ->name('account.destroy');
