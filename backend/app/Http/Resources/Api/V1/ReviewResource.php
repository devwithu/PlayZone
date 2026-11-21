<?php

namespace App\Http\Resources\Api\V1;

use App\Models\GameReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 리뷰 (api-spec §7.3). 목록과 상세가 같은 모양이다. 상세에는 `game`이 더 들어간다.
 * `forViewer()`로 보는 회원을 알려 주면 `is_mine`, `is_liked`를 채운다(비회원은 null).
 * 본인 리뷰에는 상태(`status`, `hidden_reason`)를 준다.
 *
 * @mixin GameReview
 */
class ReviewResource extends JsonResource
{
    private ?User $viewer = null;

    private bool $liked = false;

    public function forViewer(?User $viewer, bool $liked = false): static
    {
        $this->viewer = $viewer;
        $this->liked = $liked;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mine = $this->viewer !== null && $this->viewer->id === $this->user_id;

        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            /** 상세에서만. `{id, title, slug}` */
            'game' => $this->whenLoaded('game', fn () => [
                'id' => $this->game->id,
                'title' => $this->game->title,
                'slug' => $this->game->slug,
            ]),
            /** 작성자. 탈퇴한 회원이면 `id`가 null, 닉네임은 "탈퇴한 회원" */
            'author' => Author::from($this->user),
            /** 플레이한 플랫폼 */
            'platform' => ['id' => $this->platform->id, 'name' => $this->platform->name, 'slug' => $this->platform->slug],
            'title' => $this->title,
            'content' => $this->content,
            /** 종합 점수 1~10 */
            'overall_score' => $this->overall_score,
            /** 세부 점수 `{slug: score}`. 입력한 항목만. 항목 이름은 `GET /rating-categories` */
            'ratings' => (object) $this->ratingMap(),
            /** true면 본문을 접어서 보여 준다 */
            'has_spoiler' => $this->has_spoiler,
            'like_count' => $this->like_count,
            'comment_count' => $this->comment_count,
            /** 마지막 수정 시각 (UTC). 수정한 적 없으면 null. 있으면 "수정됨" 표시 */
            'edited_at' => $this->edited_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at->toIso8601ZuluString(),
            /** 내 리뷰인지. 비회원이면 null */
            'is_mine' => $this->viewer === null ? null : $mine,
            /** 내가 추천했는지. 비회원이면 null */
            'is_liked' => $this->viewer === null ? null : $this->liked,
            /** 본인에게만: `published`, `blinded`(검토 중), `hidden`(숨김) */
            'status' => $this->when($mine, fn () => $this->status->value),
            /** 본인에게만: 관리자 숨김 사유 */
            'hidden_reason' => $this->when($mine, fn () => $this->hidden_reason),
        ];
    }
}
