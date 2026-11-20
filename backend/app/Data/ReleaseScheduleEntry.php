<?php

namespace App\Data;

use App\Models\Game;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use Illuminate\Support\Collection;

/**
 * 출시 일정의 한 줄. 같은 게임이 같은 날(같은 정밀도·상태)에 여러 플랫폼으로 나오면 한 줄로 묶는다.
 * `$release`는 날짜 필드(release_date, date_precision, release_status)만 담은 저장되지 않은 모델이다.
 */
final readonly class ReleaseScheduleEntry
{
    /**
     * @param  Collection<int, Platform>  $platforms
     */
    public function __construct(
        public Game $game,
        public GameReleaseDate $release,
        public Collection $platforms,
    ) {}
}
