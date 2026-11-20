<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameReleaseDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 게임 목록 카드 (api-spec §7.2 GET /games).
 *
 * @mixin Game
 */
class GameListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primary = $this->primaryRelease();

        return [
            'id' => $this->id,
            'title' => $this->title,
            /** 웹 주소 `/games/{slug}` (공유 링크용) */
            'slug' => $this->slug,
            /** 표지 이미지 전체 URL. 없으면 null */
            'cover_url' => $this->cover?->url(),
            'genres' => TaxonomyResource::collection($this->genres),
            'platforms' => TaxonomyResource::collection($this->platforms),
            /** 공개 리뷰 수 */
            'review_count' => $this->review_count,
            /** 종합 점수 평균 (소수 첫째 자리). 리뷰 3개 미만이면 null */
            'rating_avg' => $this->rating_avg,
            /** 한 플랫폼이라도 출시되어 리뷰를 쓸 수 있는지 */
            'is_reviewable' => $this->releaseDates->contains(fn (GameReleaseDate $r) => $r->release_status === ReleaseStatus::Released),
            /** 대표 출시일: 출시됐으면 가장 이른 출시일, 아니면 가장 가까운 예정일. 계획이 없으면 null */
            'primary_release' => $primary === null ? null : [
                'release_date' => $primary->release_date?->toDateString(),
                'release_status' => $primary->release_status,
                'display_date' => $primary->displayDate(),
            ],
        ];
    }
}
