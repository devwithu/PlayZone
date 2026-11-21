<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 출석 체크 (REQ-501~503, 화면 설계 W-17 출석 탭). 앱 API와 같은 AttendanceService를 쓴다.
 * 마이페이지 탭 화면을 만들면 그 안으로 옮긴다. 주소창의 잘못된 month는 이번 달로 보여 준다.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $today = ServiceDate::today();
        $month = $request->string('month')->value();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1
            ? (CarbonImmutable::createFromFormat('!Y-m', $month, ServiceDate::TIMEZONE) ?: $today)
            : $today;

        return Inertia::render('attendance/Index', [
            'status' => $this->attendance->today($user),
            'calendar' => $this->attendance->month($user, $month),
            'prevMonth' => $month->subMonthNoOverflow()->format('Y-m'),
            // 미래 달로는 넘기지 않는다
            'nextMonth' => $month->startOfMonth()->lt($today->startOfMonth()) ? $month->addMonthNoOverflow()->format('Y-m') : null,
        ]);
    }

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
