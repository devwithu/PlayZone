<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaginatedRequest;
use App\Http\Requests\Api\V1\ReviewCommentRequest;
use App\Http\Resources\Api\V1\ReviewCommentResource;
use App\Http\Responses\ApiResponse;
use App\Models\ReviewComment;
use App\Models\User;
use App\Services\ReviewCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 리뷰 댓글 (api-spec §7.3, 리뷰 정책 §11). 목록은 로그인 없이, 쓰기는 가입을 마친 회원만.
 */
class ReviewCommentController extends Controller
{
    public function __construct(private readonly ReviewCommentService $comments) {}

    /**
     * 댓글 목록
     *
     * 공개 리뷰의 댓글을 오래된 순으로. 삭제·숨김 댓글은 `content`가 null이고 `placeholder`("삭제된 댓글입니다.")를 보여 준다.
     */
    public function index(PaginatedRequest $request, int $review): JsonResponse
    {
        /** @var User|null $viewer */
        $viewer = $request->user('sanctum');
        $page = $this->comments->listFor($review, $request->perPage());
        $page->through(fn (ReviewComment $c) => (new ReviewCommentResource($c))->forViewer($viewer));

        return ApiResponse::paginated($page->withQueryString());
    }

    /**
     * 댓글 작성
     *
     * 1~500자. 공개 리뷰에만. 댓글 포인트를 `data.point`로 준다(일일 한도는 게시판 댓글과 합산).
     */
    public function store(ReviewCommentRequest $request, int $review): JsonResponse
    {
        $user = $this->user($request);
        $result = $this->comments->create($user, $review, $request->commentContent());

        return ApiResponse::success([
            'comment' => (new ReviewCommentResource($result->model))->forViewer($user),
            'point' => $result->point->toArray(),
        ], '댓글을 등록했습니다.', 201);
    }

    /**
     * 댓글 수정
     *
     * 본인의 공개 댓글만.
     */
    public function update(ReviewCommentRequest $request, int $comment): JsonResponse
    {
        $user = $this->user($request);
        $model = $this->comments->update($user, $this->find($comment), $request->commentContent());

        return ApiResponse::success((new ReviewCommentResource($model))->forViewer($user), '댓글을 수정했습니다.');
    }

    /**
     * 댓글 삭제
     *
     * 본인 댓글만. 목록에는 "삭제된 댓글입니다."로 남는다.
     */
    public function destroy(Request $request, int $comment): JsonResponse
    {
        $this->comments->delete($this->user($request), $this->find($comment));

        return ApiResponse::success(null, '댓글을 삭제했습니다.');
    }

    private function find(int $id): ReviewComment
    {
        return ReviewComment::query()->find($id) ?? throw ApiException::of(ApiErrorCode::NotFound, '댓글을 찾을 수 없습니다.');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
