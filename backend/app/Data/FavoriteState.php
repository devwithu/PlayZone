<?php

namespace App\Data;

/**
 * 로그인 회원 기준 한 게임의 관심·출시 알림 상태 (api-spec §7.4, §7.7).
 */
final readonly class FavoriteState
{
    public function __construct(
        public bool $isFavorite,
        public bool $releaseAlert,
        public int $favoriteCount,
    ) {}

    /**
     * @return array{is_favorite: bool, release_alert: bool, favorite_count: int}
     */
    public function toArray(): array
    {
        return [
            'is_favorite' => $this->isFavorite,
            'release_alert' => $this->releaseAlert,
            'favorite_count' => $this->favoriteCount,
        ];
    }
}
