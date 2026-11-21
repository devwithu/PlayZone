<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReviewIndexRequest;
use App\Http\Requests\Api\V1\ReviewWriteRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\GameReview;
use App\Models\User;
use App\Services\GameService;
use App\Services\ReviewLikeService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 리뷰·추천 (api-spec §7.3, 리뷰 정책). 목록·상세는 로그인 없이, 쓰기는 가입을 마친 회원만.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly ReviewLikeService $likes,
        private readonly GameService $games,
    ) {}

    /**
     * 게임 리뷰 목록
     *
     * 공개 리뷰만. `sort`: `likes`(추천순, 기본), `latest`, `score_high`, `score_low`.
     * Bearer 토큰을 보내면 `is_mine`, `is_liked`를 채운다(비회원은 null).
     */
    public function index(ReviewIndexRequest $request, int $game): JsonResponse
    {
        $viewer = $this->viewer($request);
        $page = $this->reviews->listForGame($this->games->findPublished($game), $request->sort(), $request->perPage());
        $liked = $this->reviews->likedIds($viewer, array_map(fn (GameReview $r): int => $r->id, $page->items()));

        $page->through(fn (GameReview $r) => (new ReviewResource($r))->forViewer($viewer, in_array($r->id, $liked, true)));

        return ApiResponse::paginated($page->withQueryString());
    }

    /**
     * 리뷰 작성
     *
     * 게임마다 1개. 출시된 플랫폼이 있는 게임만 쓸 수 있다. 작성 포인트를 `data.point`로 준다(처음 한 번만).
     * 실패 순서: `GAME_NOT_RELEASED` → `REVIEW_HIDDEN_EXISTS` → `REVIEW_ALREADY_EXISTS`(`errors.review_id`) → `VALIDATION_FAILED`.
     */
    public function store(ReviewWriteRequest $request, int $game): JsonResponse
    {
        $user = $this->user($request);
        $result = $this->reviews->create($user, $request->game(), $request->reviewInput());

        return ApiResponse::success([
            'review' => (new ReviewResource($result->model))->forViewer($user),
            'point' => $result->point->toArray(),
        ], $result->point->earned > 0 ? "리뷰를 등록했습니다. {$result->point->earned}P를 받았습니다." : '리뷰를 등록했습니다.', 201);
    }

    /**
     * 리뷰 상세
     *
     * 공개 리뷰는 누구나 본다. 검토 중·숨김 리뷰는 작성자 본인만 보고(`status`, `hidden_reason` 포함), 그 밖에는 404.
     */
    public function show(Request $request, int $review): JsonResponse
    {
        $viewer = $this->viewer($request);
        $model = $this->reviews->findVisible($review, $viewer);

        return ApiResponse::success((new ReviewResource($model))->forViewer($viewer, $this->reviews->likedIds($viewer, [$model->id]) !== []));
    }

    /**
     * 리뷰 수정
     *
     * 본인의 공개 리뷰만. 요청 본문은 작성과 같고 전체를 바꾼다(`ratings`에서 뺀 항목은 지워진다). 수정 전 내용은 이력으로 남는다.
     * 남의 리뷰는 `403 FORBIDDEN`, 검토 중·숨김 리뷰는 `409 NOT_EDITABLE`.
     */
    public function update(ReviewWriteRequest $request, int $review): JsonResponse
    {
        $user = $this->user($request);
        $model = $this->reviews->update($user, $this->reviews->find($review), $request->reviewInput());

        return ApiResponse::success((new ReviewResource($model))->forViewer($user, $this->reviews->likedIds($user, [$model->id]) !== []), '리뷰를 수정했습니다.');
    }

    /**
     * 리뷰 삭제
     *
     * 본인 리뷰만. 같은 게임에 다시 쓸 수 있지만 작성 포인트는 다시 받지 않는다. 검토 중·숨김 리뷰는 `409 NOT_EDITABLE`.
     */
    public function destroy(Request $request, int $review): JsonResponse
    {
        $this->reviews->delete($this->user($request), $this->reviews->find($review));

        return ApiResponse::success(null, '리뷰를 삭제했습니다.');
    }

    /**
     * 리뷰 추천
     *
     * 멱등. 이미 추천했어도 성공한다. 본인 리뷰는 `409 CANNOT_TARGET_OWN`. 작성자는 추천한 회원당 한 번 포인트를 받는다.
     */
    public function like(Request $request, int $review): JsonResponse
    {
        return ApiResponse::success($this->likes->like($this->user($request), $review));
    }

    /**
     * 리뷰 추천 취소
     *
     * 멱등. 추천하지 않았어도 성공한다.
     */
    public function unlike(Request $request, int $review): JsonResponse
    {
        return ApiResponse::success($this->likes->unlike($this->user($request), $review));
    }

    private function viewer(Request $request): ?User
    {
        /** @var User|null */
        return $request->user('sanctum');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
