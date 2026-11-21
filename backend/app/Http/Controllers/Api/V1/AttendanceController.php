<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttendanceHistoryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 출석 (api-spec §7.6, REQ-501~503). 가입을 마친 회원만 쓴다. 날짜와 포인트는 서버가 정한다.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    /**
     * 오늘 출석 상태
     *
     * 오늘(KST) 출석 여부, 지금까지의 연속 일수, 출석 포인트, 다음 연속 출석 보너스.
     * `next_bonus.days_left`는 앞으로 며칠 더 출석하면 보너스를 받는지다(1이면 다음 출석에 받음).
     */
    public function today(Request $request): JsonResponse
    {
        return ApiResponse::success($this->attendance->today($this->user($request)));
    }

    /**
     * 출석하기
     *
     * 요청 본문은 없다. 출석 포인트와, 7일째·30일째면 연속 출석 보너스를 지급한다.
     * `data.point.earned`는 기본과 보너스를 합친 값이다. 오늘 이미 출석했으면 409 `ATTENDANCE_ALREADY_DONE`.
     */
    public function store(Request $request): JsonResponse
    {
        $result = $this->attendance->check($this->user($request));

        return ApiResponse::success($result->toArray(), $result->message(), 201);
    }

    /**
     * 월별 출석 기록
     *
     * 그 달에 출석한 날과 그날의 연속 일수.
     */
    public function history(AttendanceHistoryRequest $request): JsonResponse
    {
        return ApiResponse::success($this->attendance->month($this->user($request), $request->month()));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
