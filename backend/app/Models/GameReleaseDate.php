<?php

namespace App\Models;

use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GameReleaseDateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 플랫폼별 출시일 (ERD §4). 게임·플랫폼 조합당 하나.
 *
 * 출시일은 KST 기준 날짜다. 월·분기·연 단위로만 알면 그 기간의 첫날을 저장하고(저장할 때 자동 보정),
 * 미정(tba)이면 NULL이다. 화면에는 displayDate()로 "2027년 2분기"처럼 보여 준다.
 *
 * @property int $id
 * @property int $game_id
 * @property int $platform_id
 * @property CarbonImmutable|null $release_date
 * @property ReleaseDatePrecision $date_precision
 * @property ReleaseStatus $release_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['platform_id', 'release_date', 'date_precision', 'release_status'])]
class GameReleaseDate extends Model
{
    /** @use HasFactory<GameReleaseDateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'date_precision' => ReleaseDatePrecision::class,
            'release_status' => ReleaseStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // 월·분기·연 단위는 그 기간의 첫날로, 미정은 NULL로 맞춘다 (DB CHECK와 같은 규칙)
        static::saving(function (GameReleaseDate $releaseDate) {
            $date = $releaseDate->release_date;

            $releaseDate->release_date = match ($releaseDate->date_precision) {
                ReleaseDatePrecision::Tba => null,
                ReleaseDatePrecision::Day => $date,
                ReleaseDatePrecision::Month => $date?->startOfMonth(),
                ReleaseDatePrecision::Quarter => $date?->startOfQuarter(),
                ReleaseDatePrecision::Year => $date?->startOfYear(),
            };
        });

        // 출시일이 있는 플랫폼은 게임의 플랫폼 목록(필터용 game_platform)에도 넣는다
        static::saved(function (GameReleaseDate $releaseDate) {
            $releaseDate->game->platforms()->syncWithoutDetaching([$releaseDate->platform_id]);
        });
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * 화면 표시용 출시일. 예: 2027년 3월 15일 / 2027년 3월 / 2027년 2분기 / 2027년 / 미정
     */
    public function displayDate(): string
    {
        $date = $this->release_date;

        if ($date === null) {
            return '미정';
        }

        return match ($this->date_precision) {
            ReleaseDatePrecision::Day => $date->format('Y년 n월 j일'),
            ReleaseDatePrecision::Month => $date->format('Y년 n월'),
            ReleaseDatePrecision::Quarter => $date->format('Y').'년 '.$date->quarter.'분기',
            ReleaseDatePrecision::Year => $date->format('Y년'),
            ReleaseDatePrecision::Tba => '미정',
        };
    }
}
