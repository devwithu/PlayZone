<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 관심 게임 (ERD §9). 회원·게임 조합당 하나. `release_alert`가 켜져 있으면 출시일 변경·출시 알림을 받는다.
 * 등록·해제는 games.favorite_count 집계와 함께 바뀌어야 하므로 FavoriteService로만 한다.
 *
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property bool $release_alert
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Game $game
 */
#[Fillable(['release_alert'])]
class FavoriteGame extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_alert' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
