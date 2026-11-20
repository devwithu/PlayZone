<?php

namespace App\Http\Resources\Api\V1;

use App\Models\GameReleaseDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 플랫폼별 출시일 (ERD §4 game_release_dates).
 *
 * @mixin GameReleaseDate
 */
class ReleaseDateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'platform' => new TaxonomyResource($this->whenLoaded('platform')),
            /** KST 기준 날짜 `YYYY-MM-DD`. 월·분기·연 단위면 그 기간의 첫날, 미정이면 null */
            'release_date' => $this->release_date?->toDateString(),
            /** day / month / quarter / year / tba */
            'date_precision' => $this->date_precision,
            /** announced / scheduled / delayed / released / cancelled */
            'release_status' => $this->release_status,
            /** 화면 표시용. 예: `2027년 3월 15일`, `2027년 2분기`, `미정` */
            'display_date' => $this->displayDate(),
        ];
    }
}
