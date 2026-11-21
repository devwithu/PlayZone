<?php

namespace App\Services;

use App\Data\PointResult;
use App\Data\WriteResult;
use App\Enums\ApiErrorCode;
use App\Enums\PointType;
use App\Enums\ReviewStatus;
use App\Exceptions\ApiException;
use App\Models\Board;
use App\Models\Post;
use App\Models\PostAttachment;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * 게시글 작성·수정·삭제·조회 (REQ-401~404, 408). 1차는 웹에서만 쓰고(MOB-008), 2차 앱 API도 이 서비스를 쓴다.
 *
 * - 작성 포인트(post_create)는 본문 20자 이상일 때, 키 `post_create:{post_id}`로 한 번만 준다. 일일 한도는 PointService가 센다.
 * - 첨부 이미지는 글당 10개까지 public 디스크 `posts/{post_id}/`에 두고 상대 경로만 저장한다.
 * - 삭제는 상태만 deleted로 바꾼다. 검토 중·숨김 글은 작성자가 수정·삭제할 수 없다(리뷰와 같은 규칙).
 */
class PostService
{
    public const MAX_ATTACHMENTS = 10;

    /** 작성 포인트를 받는 본문 최소 글자 수 (포인트 정책 §3) */
    public const MIN_CONTENT_FOR_POINTS = 20;

    public const WITH = ['user.level', 'board'];

    public function __construct(private readonly PointService $points) {}

    /**
     * 사용 중인 게시판. 없거나 꺼져 있으면 404.
     */
    public function findBoard(string $slug): Board
    {
        return Board::query()->active()->where('slug', $slug)->firstOrFail();
    }

    /**
     * 게시판의 공개 글 목록. 검색어는 제목·본문 중간 일치.
     *
     * @param  'latest'|'likes'  $sort
     * @return LengthAwarePaginator<int, Post>
     */
    public function listFor(?Board $board, string $sort, ?string $keyword, int $perPage): LengthAwarePaginator
    {
        $query = Post::query()
            ->with(self::WITH)
            ->published()
            ->whereHas('board', fn (Builder $q) => $q->active())
            ->when($board, fn (Builder $q, Board $b) => $q->where('board_id', $b->id));

        if ($keyword !== null && $keyword !== '') {
            $like = '%'.addcslashes($keyword, '\\%_').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'ilike', $like)->orWhere('content', 'ilike', $like));
        }

        $sort === 'likes'
            ? $query->orderByDesc('like_count')->orderByDesc('id')
            : $query->orderByDesc('created_at')->orderByDesc('id');

        return $query->paginate($perPage);
    }

    /**
     * @param  list<UploadedFile>  $images
     * @return WriteResult<Post>
     */
    public function create(User $user, Board $board, string $title, string $content, array $images = []): WriteResult
    {
        $this->assertAttachmentCount(count($images));
        $stored = [];

        try {
            return DB::transaction(function () use ($user, $board, $title, $content, $images, &$stored) {
                $post = Post::query()->create([
                    'board_id' => $board->id,
                    'user_id' => $user->id,
                    'title' => $title,
                    'content' => $content,
                ]);
                $stored = $this->storeImages($post, $images, 0);

                $point = mb_strlen(preg_replace('/\s+/u', '', $content) ?? '') >= self::MIN_CONTENT_FOR_POINTS
                    ? $this->points->earn($user, PointType::PostCreate, "post_create:{$post->id}", referenceType: 'post', referenceId: $post->id)
                    : new PointResult(PointType::PostCreate, 0, (int) User::query()->whereKey($user->id)->value('point_balance'));

                return new WriteResult($post, $point);
            });
        } catch (Throwable $e) {
            Storage::delete($stored);
            throw $e;
        }
    }

    /**
     * 수정. 본인의 공개 글만. 지울 첨부 ID를 빼고 새 이미지를 더한 뒤 10개를 넘으면 안 된다.
     *
     * @param  list<UploadedFile>  $images
     * @param  list<int>  $removeIds
     */
    public function update(User $user, Post $post, string $title, string $content, array $images = [], array $removeIds = []): Post
    {
        $this->assertEditable($user, $post);
        $stored = [];

        try {
            DB::transaction(function () use ($post, $title, $content, $images, $removeIds, &$stored) {
                $locked = Post::query()->lockForUpdate()->findOrFail($post->id);
                if (! $locked->isPublished()) {
                    throw ApiException::of(ApiErrorCode::NotEditable, '검토 중이거나 숨겨진 글은 수정할 수 없습니다.');
                }

                $remaining = $locked->attachments()->whereNotIn('id', $removeIds)->count();
                $this->assertAttachmentCount($remaining + count($images));

                $locked->attachments()->whereIn('id', $removeIds)->get()->each->delete();
                $locked->update(['title' => $title, 'content' => $content, 'edited_at' => now()]);
                $stored = $this->storeImages($locked, $images, (int) $locked->attachments()->max('sort_order') + 1);
            });
        } catch (Throwable $e) {
            Storage::delete($stored);
            throw $e;
        }

        return $post->refresh()->load([...self::WITH, 'attachments']);
    }

    public function delete(User $user, Post $post): void
    {
        $this->assertEditable($user, $post);

        $updated = Post::query()->whereKey($post->id)->where('status', ReviewStatus::Published)->update(['status' => ReviewStatus::Deleted]);
        if ($updated === 0) {
            throw ApiException::of(ApiErrorCode::NotEditable, '검토 중이거나 숨겨진 글은 삭제할 수 없습니다.');
        }
        $post->refresh();
    }

    /**
     * 상세. 공개 글은 누구나, 검토 중·숨김 글은 작성자만. 삭제된 글과 꺼진 게시판의 글은 404.
     */
    public function findVisible(int $id, ?User $viewer): Post
    {
        $post = Post::query()
            ->with([...self::WITH, 'attachments'])
            ->where('status', '<>', ReviewStatus::Deleted)
            ->whereHas('board', fn (Builder $q) => $q->active())
            ->find($id);

        if ($post === null || (! $post->isPublished() && $post->user_id !== $viewer?->id)) {
            throw ApiException::of(ApiErrorCode::NotFound, '게시글을 찾을 수 없습니다.');
        }

        return $post;
    }

    /**
     * 추천·댓글 대상: 사용 중인 게시판의 공개 글만. 아니면 404.
     */
    public function findPublished(int $id): Post
    {
        $post = Post::query()->published()->whereHas('board', fn (Builder $q) => $q->active())->find($id);

        return $post ?? throw ApiException::of(ApiErrorCode::NotFound, '게시글을 찾을 수 없습니다.');
    }

    /**
     * 수정·삭제 대상 (상태 무관). 없으면 404.
     */
    public function find(int $id): Post
    {
        return Post::query()->find($id) ?? throw ApiException::of(ApiErrorCode::NotFound, '게시글을 찾을 수 없습니다.');
    }

    /**
     * 조회수 (REQ-404). 같은 세션에서는 글마다 한 번만 센다.
     */
    public function recordView(Post $post, Session $session): void
    {
        /** @var list<int> $viewed */
        $viewed = $session->get('viewed_posts', []);
        if (in_array($post->id, $viewed, true)) {
            return;
        }

        Post::query()->whereKey($post->id)->increment('view_count');
        $post->view_count++;
        $session->put('viewed_posts', array_slice([...$viewed, $post->id], -200));
    }

    /**
     * 이 회원이 추천한 글인지
     */
    public function isLiked(?User $user, Post $post): bool
    {
        return $user !== null && DB::table('post_likes')->where('post_id', $post->id)->where('user_id', $user->id)->exists();
    }

    private function assertEditable(User $user, Post $post): void
    {
        if ($post->status === ReviewStatus::Deleted) {
            throw ApiException::of(ApiErrorCode::NotFound, '게시글을 찾을 수 없습니다.');
        }
        if ($post->user_id !== $user->id) {
            throw ApiException::of(ApiErrorCode::Forbidden, '본인 글만 수정·삭제할 수 있습니다.');
        }
        if (! $post->isPublished()) {
            throw ApiException::of(ApiErrorCode::NotEditable, '검토 중이거나 숨겨진 글은 수정·삭제할 수 없습니다.');
        }
    }

    private function assertAttachmentCount(int $count): void
    {
        if ($count > self::MAX_ATTACHMENTS) {
            throw ValidationException::withMessages(['images' => '이미지는 글 하나에 '.self::MAX_ATTACHMENTS.'개까지 올릴 수 있습니다.']);
        }
    }

    /**
     * @param  list<UploadedFile>  $images
     * @return list<string> 저장한 경로 (실패 시 지우기 위해)
     */
    private function storeImages(Post $post, array $images, int $startOrder): array
    {
        $paths = [];
        foreach ($images as $i => $image) {
            $path = $image->storeAs("posts/{$post->id}", Str::uuid().'.'.($image->extension() ?: 'bin'), ['visibility' => 'public']);
            if ($path === false) {
                throw new RuntimeException('이미지를 저장하지 못했습니다.');
            }
            $paths[] = $path;
            PostAttachment::query()->create([
                'post_id' => $post->id,
                'file_path' => $path,
                'original_name' => Str::limit($image->getClientOriginalName(), 250, ''),
                'mime_type' => (string) $image->getMimeType(),
                'size_bytes' => (int) $image->getSize(),
                'sort_order' => $startOrder + $i,
            ]);
        }

        return $paths;
    }
}
