<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReleaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReleaseScheduleRequest;
use App\Http\Requests\Api\V1\ReleaseUpcomingRequest;
use App\Http\Resources\Api\V1\ReleaseDateResource;
use App\Http\Resources\Api\V1\ReleaseScheduleResource;
use App\Http\Responses\ApiResponse;
use App\Models\GameReleaseDate;
use App\Services\GameService;
use App\Services\ReleaseService;
use Illuminate\Http\JsonResponse;

/**
 * 출시 일정 (api-spec §7.4). 로그인 없이 호출할 수 있고, 공개 게임만 보인다.
 * 출시 알림 켜기·끄기는 관심 게임과 함께 추가한다.
 */
class ReleaseController extends Controller
{
    public function __construct(
        private readonly ReleaseService $releases,
        private readonly GameService $games,
    ) {}

    /**
     * 기간별 출시 일정
     *
     * 출시일 기간이 `from`~`to`(KST, 기본 이번 달)와 겹치는 출시일을 기간이 끝나는 날 순으로 준다("12월 중"은 12월의 날짜별 출시 뒤). 월·분기·연 단위 출시일은
     * 그 기간 전체로 본다(예: 2027년 1분기는 1~3월 조회에 모두 포함). 미정·취소는 빠진다.
     * 같은 게임이 같은 날 여러 플랫폼으로 나오면 한 항목으로 묶는다.
     */
    public function index(ReleaseScheduleRequest $request): JsonResponse
    {
        $entries = $this->releases->schedule($request->from(), $request->to(), $request->platforms());

        return ApiResponse::success(ReleaseScheduleResource::collection($entries));
    }

    /**
     * 출시 예정
     *
     * 아직 출시되지 않았고 출시일이 오늘(KST) 이후인 게임을 가까운 순(기간이 끝나는 날 기준)으로. 출시일 미정은 맨 뒤에 온다.
     */
    public function upcoming(ReleaseUpcomingRequest $request): JsonResponse
    {
        $entries = $this->releases->upcoming($request->platforms(), $request->perPage());

        return ApiResponse::paginated($entries->withQueryString(), ReleaseScheduleResource::class);
    }

    /**
     * 게임별 플랫폼 출시일
     *
     * 게임 상세의 `release_dates`와 같다(출시일 순, 미정은 뒤로). 취소된 플랫폼도 상태와 함께 준다.
     */
    public function game(int $game): JsonResponse
    {
        $releaseDates = $this->games->findPublished($game)->releaseDates
            ->sortBy(fn (GameReleaseDate $r) => [$r->release_date?->toDateString() ?? '9999-12-31', $r->release_status === ReleaseStatus::Cancelled])
            ->values();

        return ApiResponse::success(ReleaseDateResource::collection($releaseDates));
    }
}
