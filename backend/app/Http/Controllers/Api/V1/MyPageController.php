<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaginatedRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\GameReview;
use App\Models\User;
use App\Services\MyPageService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 마이페이지 (api-spec §7.7). 가입을 마친 회원만 쓴다.
 * 내 게시글(`/mypage/posts`)은 커뮤니티와 함께 2차에 붙인다(1차 앱에는 커뮤니티가 없다, MOB-008).
 */
class MyPageController extends Controller
{
    public function __construct(
        private readonly MyPageService $mypage,
        private readonly ReviewService $reviews,
    ) {}

    /**
     * 마이페이지 대시보드
     *
     * 닉네임, 잔액, 누적 포인트, 등급과 다음 등급(`GET /auth/me`와 같은 모양), 출석(`attended_today`, `streak_days`, `month_count`: 이번 달 출석 일수),
     * 작성 수(`counts.reviews`, `counts.posts`: 검토 중·숨김 포함, 삭제 제외), 관심 게임 수(`counts.favorites`).
     */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->mypage->dashboard($this->user($request)));
    }

    /**
     * 내 리뷰
     *
     * 최근 작성 순. 검토 중(`blinded`)·숨김(`hidden`) 리뷰도 `status`, `hidden_reason`과 함께 준다. 삭제한 리뷰는 빠진다.
     * 항목은 리뷰 상세와 같은 모양이다(`game` 포함).
     */
    public function reviews(PaginatedRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $page = $this->mypage->reviews($user, $request->perPage());
        $liked = $this->reviews->likedIds($user, array_map(fn (GameReview $r): int => $r->id, $page->items()));

        $page->through(fn (GameReview $r) => (new ReviewResource($r))->forViewer($user, in_array($r->id, $liked, true)));

        return ApiResponse::paginated($page->withQueryString());
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
