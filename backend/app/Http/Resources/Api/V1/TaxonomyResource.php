<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Genre;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 장르·플랫폼 (api-spec §7.2 /genres, /platforms). `slug`를 게임 목록 필터(`genre`, `platform`)에 쓴다.
 *
 * @mixin Genre|Platform
 */
class TaxonomyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
