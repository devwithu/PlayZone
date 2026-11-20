<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WithdrawRequest;
use App\Services\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * 웹 회원 탈퇴 (W-18 설정, REQ-009). 앱은 DELETE /api/v1/auth/me.
 */
class AccountController extends Controller
{
    public function destroy(WithdrawRequest $request, WithdrawalService $service): RedirectResponse
    {
        $service->withdraw($request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::flash('toast', ['type' => 'success', 'message' => '탈퇴되었습니다. 그동안 이용해 주셔서 감사합니다.']);

        return to_route('home');
    }
}
