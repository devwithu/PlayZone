<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\CommentStatus;
use App\Models\ReviewComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 리뷰 댓글 (api-spec §7.3). 삭제·숨김 댓글은 `content`가 null이고 `placeholder`에 표시 문구가 있다.
 *
 * @mixin ReviewComment
 */
class ReviewCommentResource extends JsonResource
{
    private ?User $viewer = null;

    public function forViewer(?User $viewer): static
    {
        $this->viewer = $viewer;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $published = $this->status === CommentStatus::Published;

        return [
            'id' => $this->id,
            'review_id' => $this->review_id,
            /** 삭제·숨김 댓글이면 null */
            'author' => $published ? Author::from($this->user) : null,
            /** 삭제·숨김 댓글이면 null */
            'content' => $published ? $this->content : null,
            /** `published`, `hidden`, `deleted` */
            'status' => $this->status->value,
            /** 내용 대신 보여 줄 문구. 예: "삭제된 댓글입니다." 공개 댓글이면 null */
            'placeholder' => $this->status->placeholder(),
            'is_edited' => $published && $this->isEdited(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            /** 내 댓글인지. 비회원이면 null */
            'is_mine' => $this->viewer === null ? null : $this->viewer->id === $this->user_id,
        ];
    }
}
