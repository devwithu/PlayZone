<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\GameImageType;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use App\Models\GameImage;
use App\Models\GameReleaseDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 게임 상세 (api-spec §7.2 GET /games/{id}).
 *
 * 세부 항목 평균은 리뷰(3단계), 내 리뷰 ID·관심 여부·출시 알림은 관심 게임 작업에서 추가한다.
 *
 * @mixin Game
 */
class GameDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $images = $this->images;
        $cover = $images->first(fn (GameImage $image) => $image->image_type === GameImageType::Cover);

        return [
            'id' => $this->id,
            'title' => $this->title,
            /** 웹 주소 `/games/{slug}` (공유 링크용) */
            'slug' => $this->slug,
            'description' => $this->description,
            'developer' => $this->developer,
            'publisher' => $this->publisher,
            'genres' => TaxonomyResource::collection($this->genres),
            'platforms' => TaxonomyResource::collection($this->platforms),
            /** 표지 이미지 전체 URL. 없으면 null */
            'cover_url' => $cover?->url(),
            /** 스크린샷 전체 URL (관리자가 정한 순서) */
            'screenshot_urls' => $images
                ->filter(fn (GameImage $image) => $image->image_type === GameImageType::Screenshot)
                ->map(fn (GameImage $image) => $image->url())
                ->values()
                ->all(),
            /** 배너 이미지 전체 URL. 없으면 null */
            'banner_url' => $images->first(fn (GameImage $image) => $image->image_type === GameImageType::Banner)?->url(),
            /** 플랫폼별 출시일 (출시일 순, 미정은 뒤로) */
            'release_dates' => ReleaseDateResource::collection(
                $this->releaseDates
                    ->sortBy(fn (GameReleaseDate $r) => $r->release_date?->toDateString() ?? '9999-12-31')
                    ->values(),
            ),
            /** 한 플랫폼이라도 출시되어 리뷰를 쓸 수 있는지 (리뷰 정책 §2) */
            'is_reviewable' => $this->releaseDates->contains(fn (GameReleaseDate $r) => $r->release_status === ReleaseStatus::Released),
            /** 공개 리뷰 수 */
            'review_count' => $this->review_count,
            /** 종합 점수 평균 (소수 첫째 자리). 리뷰 3개 미만이면 null */
            'rating_avg' => $this->rating_avg,
            'favorite_count' => $this->favorite_count,
        ];
    }
}
