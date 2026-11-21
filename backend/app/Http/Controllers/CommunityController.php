<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\ReviewCommentRequest;
use App\Http\Requests\PostWriteRequest;
use App\Http\Resources\Api\V1\Author;
use App\Models\Board;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\PostComment;
use App\Models\User;
use App\Services\PostInteractionService;
use App\Services\PostService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 커뮤니티 (화면 설계 W-09~W-12, REQ-401~408). 1차는 웹에서만 제공한다(MOB-008).
 * 업무 규칙 오류(ApiException)는 오류 화면 대신 토스트로 알리고 보던 화면으로 돌아간다.
 * 주소창에서 잘못 바뀐 sort·q는 오류 대신 기본값으로 보여 준다.
 */
class CommunityController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly PostService $posts,
        private readonly PostInteractionService $interactions,
    ) {}

    /**
     * W-09 커뮤니티 홈: 게시판 목록과 전체 최신 글
     */
    public function index(Request $request): Response
    {
        $latest = $this->posts->listFor(null, 'latest', null, 15);

        return Inertia::render('community/Index', [
            'boards' => $this->boards(),
            'posts' => array_map(fn (Post $p) => $this->listItem($p), $latest->items()),
        ]);
    }

    /**
     * W-10 게시글 목록
     */
    public function board(Request $request, string $board): Response
    {
        $model = $this->posts->findBoard($board);
        $sort = $request->string('sort')->value() === 'likes' ? 'likes' : 'latest';
        $keyword = trim($request->string('q')->value());
        $tooShort = $keyword !== '' && mb_strlen($keyword) < 2;
        $page = $this->posts->listFor($model, $sort, $tooShort ? null : $keyword, self::PER_PAGE);

        return Inertia::render('community/Board', [
            'boards' => $this->boards(),
            'board' => $this->boardData($model),
            'posts' => [
                'items' => array_map(fn (Post $p) => $this->listItem($p), $page->items()),
                'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            ],
            'filters' => ['sort' => $sort, 'q' => $keyword],
            'keywordError' => $tooShort ? '2자 이상 입력해 주세요.' : null,
        ]);
    }

    /**
     * W-11 게시글 상세 (공개, SSR). 조회수는 세션당 한 번 오른다.
     */
    public function show(Request $request, int $post): Response
    {
        $viewer = $this->viewer($request);
        $model = $this->posts->findVisible($post, $viewer);
        if ($model->isPublished()) {
            $this->posts->recordView($model, $request->session());
        }
        $comments = $model->isPublished() ? $this->interactions->comments($model->id, 100) : null;
        $mine = $viewer !== null && $viewer->id === $model->user_id;

        return Inertia::render('community/Show', [
            'post' => [
                ...$this->listItem($model),
                'content' => $model->content,
                'images' => $model->attachments->map(fn (PostAttachment $a) => ['id' => $a->id, 'url' => $a->url(), 'name' => $a->original_name])->values()->all(),
                'edited_at' => $model->edited_at?->toIso8601ZuluString(),
                'is_mine' => $viewer === null ? null : $mine,
                'is_liked' => $viewer === null ? null : $this->posts->isLiked($viewer, $model),
                'status' => $mine ? $model->status->value : null,
                'hidden_reason' => $mine ? $model->hidden_reason : null,
            ],
            'comments' => $comments === null ? null : array_map(fn (PostComment $c) => [
                'id' => $c->id,
                'author' => $c->status->placeholder() === null ? Author::from($c->user) : null,
                'content' => $c->status->placeholder() === null ? $c->content : null,
                'status' => $c->status->value,
                'placeholder' => $c->status->placeholder(),
                'is_edited' => $c->status->placeholder() === null && $c->isEdited(),
                'created_at' => $c->created_at->toIso8601ZuluString(),
                'is_mine' => $viewer === null ? null : $viewer->id === $c->user_id,
            ], $comments->items()),
            'meta' => [
                'description' => Str::limit(trim(preg_replace('/\s+/u', ' ', $model->content) ?? ''), 150),
                'image' => $model->attachments->first()?->url(),
                'url' => route('posts.show', $model->id),
            ],
        ]);
    }

    /**
     * W-12 글쓰기
     */
    public function create(Request $request, string $board): Response
    {
        return $this->form($this->posts->findBoard($board), null);
    }

    public function store(PostWriteRequest $request): RedirectResponse
    {
        $result = $this->posts->create($this->user($request), $request->board(), $request->title(), $request->body(), $request->images());
        $earned = $result->point->earned;
        Inertia::flash('toast', ['type' => 'success', 'message' => $earned > 0 ? "글을 등록했습니다. {$earned}P를 받았습니다." : '글을 등록했습니다.']);

        return to_route('posts.show', $result->model->getKey());
    }

    public function edit(Request $request, int $post): Response|RedirectResponse
    {
        $model = $this->posts->find($post);
        if ($model->user_id !== $this->user($request)->id || ! $model->isPublished()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $model->isPublished() ? '본인 글만 수정할 수 있습니다.' : '검토 중이거나 숨겨진 글은 수정할 수 없습니다.']);

            return to_route('posts.show', $model->id);
        }

        return $this->form($model->board, $model->load('attachments'));
    }

    public function update(PostWriteRequest $request, int $post): RedirectResponse
    {
        return $this->attempt(function () use ($request, $post) {
            $this->posts->update($this->user($request), $this->posts->find($post), $request->title(), $request->body(), $request->images(), $request->removeIds());
            Inertia::flash('toast', ['type' => 'success', 'message' => '글을 수정했습니다.']);

            return to_route('posts.show', $post);
        });
    }

    public function destroy(Request $request, int $post): RedirectResponse
    {
        return $this->attempt(function () use ($request, $post) {
            $model = $this->posts->find($post);
            $this->posts->delete($this->user($request), $model);
            Inertia::flash('toast', ['type' => 'success', 'message' => '글을 삭제했습니다.']);

            return to_route('community.board', $model->board->slug);
        });
    }

    public function like(Request $request, int $post): RedirectResponse
    {
        return $this->attempt(function () use ($request, $post) {
            $this->interactions->like($this->user($request), $post);

            return back();
        });
    }

    public function unlike(Request $request, int $post): RedirectResponse
    {
        return $this->attempt(function () use ($request, $post) {
            $this->interactions->unlike($this->user($request), $post);

            return back();
        });
    }

    public function storeComment(ReviewCommentRequest $request, int $post): RedirectResponse
    {
        return $this->attempt(function () use ($request, $post) {
            $result = $this->interactions->comment($this->user($request), $post, $request->commentContent());
            $earned = $result->point->earned;
            Inertia::flash('toast', ['type' => 'success', 'message' => $earned > 0 ? "댓글을 등록했습니다. {$earned}P를 받았습니다." : '댓글을 등록했습니다.']);

            return back();
        });
    }

    public function updateComment(ReviewCommentRequest $request, int $comment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $comment) {
            $this->interactions->updateComment($this->user($request), PostComment::query()->findOrFail($comment), $request->commentContent());

            return back();
        });
    }

    public function destroyComment(Request $request, int $comment): RedirectResponse
    {
        return $this->attempt(function () use ($request, $comment) {
            $this->interactions->deleteComment($this->user($request), PostComment::query()->findOrFail($comment));

            return back();
        });
    }

    private function form(Board $board, ?Post $post): Response
    {
        return Inertia::render('community/Form', [
            'boards' => $this->boards(),
            'board' => $this->boardData($board),
            'post' => $post === null ? null : [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'images' => $post->attachments->map(fn (PostAttachment $a) => ['id' => $a->id, 'url' => $a->url(), 'name' => $a->original_name])->values()->all(),
            ],
            'limits' => ['max_images' => PostService::MAX_ATTACHMENTS, 'image_max_mb' => PostWriteRequest::IMAGE_MAX_KB / 1024, 'min_content_for_points' => PostService::MIN_CONTENT_FOR_POINTS],
        ]);
    }

    /**
     * @return list<array{id: int, name: string, slug: string, description: string|null}>
     */
    private function boards(): array
    {
        return array_values(Board::query()->active()->ordered()->get()->map(fn (Board $b) => $this->boardData($b))->all());
    }

    /**
     * @return array{id: int, name: string, slug: string, description: string|null}
     */
    private function boardData(Board $board): array
    {
        return ['id' => $board->id, 'name' => $board->name, 'slug' => $board->slug, 'description' => $board->description];
    }

    /**
     * @return array<string, mixed>
     */
    private function listItem(Post $post): array
    {
        return [
            'id' => $post->id,
            'board' => ['name' => $post->board->name, 'slug' => $post->board->slug],
            'title' => $post->title,
            'author' => Author::from($post->user),
            'view_count' => $post->view_count,
            'like_count' => $post->like_count,
            'comment_count' => $post->comment_count,
            'created_at' => $post->created_at->toIso8601ZuluString(),
        ];
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
