<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\ReviewCommentRequest;
use App\Models\ReviewComment;
use App\Models\User;
use App\Services\ReviewCommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 웹 리뷰 댓글 (화면 설계 W-06). 앱 API와 같은 ReviewCommentService를 쓴다. 처리 후 리뷰 상세로 돌아간다.
 */
class ReviewCommentController extends Controller
{
    public function __construct(private readonly ReviewCommentService $comments) {}

    public function store(ReviewCommentRequest $request, int $review): RedirectResponse
    {
        return $this->attempt(function () use ($request, $review) {
            $result = $this->comments->create($this->user($request), $review, $request->commentContent());
            $earned = $result->point->earned;
            Inertia::flash('toast', ['type' => 'success', 'message' => $earned > 0 ? "댓글을 등록했습니다. {$earned}P를 받았습니다." : '댓글을 등록했습니다.']);
        });
    }

    public function update(ReviewCommentRequest $request, int $comment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $comment) {
            $this->comments->update($this->user($request), ReviewComment::query()->findOrFail($comment), $request->commentContent());
        });
    }

    public function destroy(Request $request, int $comment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $comment) {
            $this->comments->delete($this->user($request), ReviewComment::query()->findOrFail($comment));
        });
    }

    /**
     * @param  callable(): void  $action
     */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (ApiException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
