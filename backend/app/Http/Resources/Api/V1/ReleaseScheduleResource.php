<?php

namespace App\Http\Resources\Api\V1;

use App\Data\ReleaseScheduleEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 출시 일정 한 줄 (api-spec §7.4). 같은 게임이 같은 날 여러 플랫폼으로 나오면 한 줄로 묶는다.
 *
 * @property ReleaseScheduleEntry $resource
 */
class ReleaseScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'game' => [
                'id' => $entry->game->id,
                'title' => $entry->game->title,
                /** 웹 주소 `/games/{slug}` */
                'slug' => $entry->game->slug,
                /** 표지 이미지 전체 URL. 없으면 null */
                'cover_url' => $entry->game->cover?->url(),
            ],
            /** 이 날짜에 나오는 플랫폼 (관리자가 정한 순서) */
            'platforms' => TaxonomyResource::collection($entry->platforms),
            /** KST 날짜 `YYYY-MM-DD`. 월·분기·연 단위면 그 기간의 첫날, 미정이면 null */
            'release_date' => $entry->release->release_date?->toDateString(),
            /** day / month / quarter / year / tba */
            'date_precision' => $entry->release->date_precision,
            /** announced / scheduled / delayed / released */
            'release_status' => $entry->release->release_status,
            /** 화면 표시용. 예: `2027년 3월 15일`, `2027년 2분기`, `미정` */
            'display_date' => $entry->release->displayDate(),
        ];
    }
}
