<?php

namespace App\Services;

use App\Data\WriteResult;
use App\Enums\ApiErrorCode;
use App\Enums\CommentStatus;
use App\Enums\PointType;
use App\Enums\ReviewStatus;
use App\Exceptions\ApiException;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 게시글 추천·댓글 (REQ-405, 406). 규칙은 리뷰 추천·댓글과 같다(리뷰 정책 §10·§11).
 *
 * - 추천: UNIQUE (user_id, post_id) + insertOrIgnore로 멱등. 본인 글은 추천할 수 없다. 게시글 추천에는 포인트가 없다(포인트 정책 §3).
 * - 댓글: 작성 포인트 comment_create, 키 `comment_create:post:{comment_id}`. comment_count는 공개 댓글 수.
 */
class PostInteractionService
{
    public function __construct(
        private readonly PostService $posts,
        private readonly PointService $points,
    ) {}

    /**
     * @return array{liked: bool, like_count: int}
     */
    public function like(User $user, int $postId): array
    {
        $post = $this->posts->findPublished($postId);
        if ($post->user_id === $user->id) {
            throw ApiException::of(ApiErrorCode::CannotTargetOwn, '본인 글은 추천할 수 없습니다.');
        }

        DB::transaction(function () use ($user, $post) {
            $inserted = DB::table('post_likes')->insertOrIgnore(['post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now()]);
            if ($inserted > 0) {
                Post::query()->whereKey($post->id)->increment('like_count');
            }
        });

        return $this->likeState($user, $post->id);
    }

    /**
     * @return array{liked: bool, like_count: int}
     */
    public function unlike(User $user, int $postId): array
    {
        $post = Post::query()->where('status', '<>', ReviewStatus::Deleted)->find($postId)
            ?? throw ApiException::of(ApiErrorCode::NotFound, '게시글을 찾을 수 없습니다.');

        DB::transaction(function () use ($user, $post) {
            $deleted = DB::table('post_likes')->where('post_id', $post->id)->where('user_id', $user->id)->delete();
            if ($deleted > 0) {
                Post::query()->whereKey($post->id)->decrement('like_count', $deleted);
            }
        });

        return $this->likeState($user, $post->id);
    }

    /**
     * 공개 글의 댓글 (오래된 순). 삭제·숨김 댓글도 자리를 지키도록 함께 준다.
     *
     * @return LengthAwarePaginator<int, PostComment>
     */
    public function comments(int $postId, int $perPage): LengthAwarePaginator
    {
        $post = $this->posts->findPublished($postId);

        return PostComment::query()
            ->with('user.level')
            ->where('post_id', $post->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @return WriteResult<PostComment>
     */
    public function comment(User $user, int $postId, string $content): WriteResult
    {
        $post = $this->posts->findPublished($postId);

        return DB::transaction(function () use ($user, $post, $content) {
            $comment = PostComment::query()->create(['post_id' => $post->id, 'user_id' => $user->id, 'content' => $content]);
            Post::query()->whereKey($post->id)->increment('comment_count');

            $point = $this->points->earn(
                $user,
                PointType::CommentCreate,
                "comment_create:post:{$comment->id}",
                referenceType: 'post_comment',
                referenceId: $comment->id,
            );

            return new WriteResult($comment, $point);
        });
    }

    public function updateComment(User $user, PostComment $comment, string $content): PostComment
    {
        $this->assertCommentEditable($user, $comment);
        $comment->update(['content' => $content]);

        return $comment;
    }

    public function deleteComment(User $user, PostComment $comment): void
    {
        $this->assertCommentEditable($user, $comment);

        DB::transaction(function () use ($comment) {
            $updated = PostComment::query()
                ->whereKey($comment->id)
                ->where('status', CommentStatus::Published)
                ->update(['status' => CommentStatus::Deleted, 'updated_at' => now()]);
            if ($updated > 0) {
                Post::query()->whereKey($comment->post_id)->decrement('comment_count');
            }
        });

        $comment->refresh();
    }

    private function assertCommentEditable(User $user, PostComment $comment): void
    {
        $this->posts->findPublished($comment->post_id);

        if ($comment->status === CommentStatus::Deleted) {
            throw ApiException::of(ApiErrorCode::NotFound, '댓글을 찾을 수 없습니다.');
        }
        if ($comment->user_id !== $user->id) {
            throw ApiException::of(ApiErrorCode::Forbidden, '본인 댓글만 수정·삭제할 수 있습니다.');
        }
        if ($comment->status !== CommentStatus::Published) {
            throw ApiException::of(ApiErrorCode::NotEditable, '숨겨진 댓글은 수정·삭제할 수 없습니다.');
        }
    }

    /**
     * @return array{liked: bool, like_count: int}
     */
    private function likeState(User $user, int $postId): array
    {
        return [
            'liked' => DB::table('post_likes')->where('post_id', $postId)->where('user_id', $user->id)->exists(),
            'like_count' => (int) Post::query()->whereKey($postId)->value('like_count'),
        ];
    }
}
