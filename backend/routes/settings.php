<?php

use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'signup.completed'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

// 회원 탈퇴: 가입 미완료 회원도 할 수 있다 (ADR-003 §4)
Route::delete('settings/account', [AccountController::class, 'destroy'])
    ->middleware(['auth', 'throttle:5,1'])
    ->name('account.destroy');
