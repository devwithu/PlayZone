<?php

namespace App\Http\Controllers;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\ReviewWriteRequest;
use App\Http\Resources\Api\V1\ReviewCommentResource;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Game;
use App\Models\GameReview;
use App\Models\Platform;
use App\Models\RatingCategory;
use App\Models\ReviewComment;
use App\Models\User;
use App\Services\GameService;
use App\Services\ReviewCommentService;
use App\Services\ReviewLikeService;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 리뷰 (화면 설계 W-05 리뷰 영역, W-06 리뷰 상세, W-07 리뷰 작성). 앱 API와 같은 서비스·검증을 쓴다.
 * 업무 규칙 오류(ApiException)는 오류 화면 대신 토스트로 알리고 보던 화면으로 돌아간다.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
        private readonly ReviewLikeService $likes,
        private readonly ReviewCommentService $comments,
        private readonly GameService $games,
    ) {}

    /**
     * 리뷰 상세 (공개, SSR). 검토 중·숨김 리뷰는 작성자 본인만 본다.
     */
    public function show(Request $request, int $review): Response
    {
        $viewer = $this->viewer($request);
        $model = $this->reviews->findVisible($review, $viewer);
        $comments = $model->isPublished() ? $this->comments->listFor($model->id, 50) : null;

        return Inertia::render('reviews/Show', [
            'review' => (new ReviewResource($model))
                ->forViewer($viewer, $this->reviews->likedIds($viewer, [$model->id]) !== [])
                ->resolve($request),
            'comments' => $comments === null ? null : [
                'items' => array_map(
                    fn (ReviewComment $c) => (new ReviewCommentResource($c))->forViewer($viewer)->resolve($request),
                    $comments->items(),
                ),
                'pagination' => ['current_page' => $comments->currentPage(), 'last_page' => $comments->lastPage(), 'total' => $comments->total()],
            ],
            'categories' => $this->categories(),
            'meta' => [
                'description' => Str::limit(trim(preg_replace('/\s+/u', ' ', $model->content) ?? ''), 150),
                'url' => route('reviews.show', $model->id),
            ],
        ]);
    }

    public function create(Request $request, string $slug): Response|RedirectResponse
    {
        $game = $this->games->findPublishedBySlug($slug);

        try {
            $this->reviews->assertCanWrite($this->user($request), $game);
        } catch (ApiException $e) {
            $existing = $e->errorCode === ApiErrorCode::ReviewAlreadyExists ? ($e->errors['review_id'] ?? null) : null;
            Inertia::flash('toast', ['type' => 'info', 'message' => $existing !== null ? '이미 쓴 리뷰가 있습니다. 리뷰를 수정해 주세요.' : $e->getMessage()]);

            return $existing !== null
                ? to_route('reviews.edit', $existing)
                : to_route('games.show', $game->slug);
        }

        return $this->form($game, null);
    }

    public function store(ReviewWriteRequest $request, int $game): RedirectResponse
    {
        $result = $this->reviews->create($this->user($request), $request->game(), $request->reviewInput());
        $earned = $result->point->earned;
        Inertia::flash('toast', ['type' => 'success', 'message' => $earned > 0 ? "리뷰를 등록했습니다. {$earned}P를 받았습니다." : '리뷰를 등록했습니다.']);

        return to_route('reviews.show', $result->model->getKey());
    }

    public function edit(Request $request, int $review): Response|RedirectResponse
    {
        $model = $this->reviews->find($review);
        if ($model->user_id !== $this->user($request)->id || ! $model->isPublished()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $model->isPublished() ? '본인 리뷰만 수정할 수 있습니다.' : '검토 중이거나 숨겨진 리뷰는 수정할 수 없습니다.']);

            return to_route('reviews.show', $model->id);
        }

        return $this->form($model->game, $model->load('ratings.category'));
    }

    public function update(ReviewWriteRequest $request, int $review): RedirectResponse
    {
        return $this->attempt(function () use ($request, $review) {
            $this->reviews->update($this->user($request), $this->reviews->find($review), $request->reviewInput());
            Inertia::flash('toast', ['type' => 'success', 'message' => '리뷰를 수정했습니다.']);

            return to_route('reviews.show', $review);
        });
    }

    public function destroy(Request $request, int $review): RedirectResponse
    {
        return $this->attempt(function () use ($request, $review) {
            $model = $this->reviews->find($review);
            $this->reviews->delete($this->user($request), $model);
            Inertia::flash('toast', ['type' => 'success', 'message' => '리뷰를 삭제했습니다.']);

            return to_route('games.show', $model->game->slug);
        });
    }

    public function like(Request $request, int $review): RedirectResponse
    {
        return $this->attempt(function () use ($request, $review) {
            $this->likes->like($this->user($request), $review);

            return back();
        });
    }

    public function unlike(Request $request, int $review): RedirectResponse
    {
        return $this->attempt(function () use ($request, $review) {
            $this->likes->unlike($this->user($request), $review);

            return back();
        });
    }

    private function form(Game $game, ?GameReview $review): Response
    {
        $platformIds = $this->reviews->releasedPlatformIds($game);

        return Inertia::render('reviews/Form', [
            'game' => ['id' => $game->id, 'title' => $game->title, 'slug' => $game->slug],
            'platforms' => Platform::query()->whereIn('id', $platformIds)->ordered()->get(['id', 'name', 'slug'])
                ->map(fn (Platform $p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug])->values()->all(),
            'categories' => $this->categories(),
            'review' => $review === null ? null : [
                'id' => $review->id,
                'title' => $review->title,
                'content' => $review->content,
                'platform_id' => $review->platform_id,
                'overall_score' => $review->overall_score,
                'ratings' => (object) $review->ratingMap(),
                'has_spoiler' => $review->has_spoiler,
            ],
        ]);
    }

    /**
     * @return list<array{name: string, slug: string}>
     */
    private function categories(): array
    {
        return array_values(RatingCategory::query()->active()->ordered()->get()
            ->map(fn (RatingCategory $c) => ['name' => $c->name, 'slug' => $c->slug])
            ->all());
    }

    /**
     * @param  callable(): RedirectResponse  $action
     */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (ApiException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }
    }

    private function viewer(Request $request): ?User
    {
        /** @var User|null */
        return $request->user();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
