<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 웹 출석 버튼 (REQ-501). 앱 API와 같은 AttendanceService를 쓴다. 화면은 마이페이지 출석 탭(MyPageController::attendance).
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function store(Request $request): RedirectResponse
    {
        try {
            $result = $this->attendance->check($this->user($request));
            Inertia::flash('toast', ['type' => 'success', 'message' => $result->message()]);
        } catch (ApiException $e) {
            if ($e->errorCode !== ApiErrorCode::AttendanceAlreadyDone) {
                throw $e;
            }
            Inertia::flash('toast', ['type' => 'info', 'message' => '오늘은 이미 출석했습니다.']);
        }

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
