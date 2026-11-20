<?php

namespace App\Http\Resources\Api\V1;

use App\Models\FavoriteGame;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 내 관심 게임 한 줄 (api-spec §7.7 GET /mypage/favorites).
 *
 * @mixin FavoriteGame
 */
class FavoriteGameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** 게임 목록 카드와 같은 모양 */
            'game' => new GameListResource($this->game),
            /** 출시 알림이 켜져 있는지 */
            'release_alert' => $this->release_alert,
            /** 관심 게임으로 등록한 시각 (UTC) */
            'favorited_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
