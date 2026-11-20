<?php

namespace App\Services;

use App\Enums\GameReleaseFilter;
use App\Enums\GameSort;
use App\Enums\ReleaseStatus;
use App\Models\Game;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * 게임 조회 (목록·검색·필터·정렬, 상세). 앱 API와 웹 화면이 함께 쓴다.
 * 사이트·앱에는 공개(published) 게임만 보인다.
 */
class GameService
{
    /** 목록 카드에 필요한 관계 */
    private const LIST_RELATIONS = ['cover', 'genres', 'platforms', 'releaseDates'];

    /**
     * @param  list<string>  $genres  장르 slug. 하나라도 해당하면 포함 (OR)
     * @param  list<string>  $platforms  플랫폼 slug. 하나라도 해당하면 포함 (OR)
     * @return LengthAwarePaginator<int, Game>
     */
    public function search(
        ?string $keyword = null,
        array $genres = [],
        array $platforms = [],
        ?GameReleaseFilter $release = null,
        GameSort $sort = GameSort::Popular,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Game::query()->published()->with(self::LIST_RELATIONS);

        if ($keyword !== null && $keyword !== '') {
            // 기본 검색은 제목 중간 일치. pg_trgm GIN 인덱스가 ILIKE를 받쳐 준다 (ERD §12-5)
            $query->where('title', 'ilike', '%'.$this->escapeLike($keyword).'%');
        }

        if ($genres !== []) {
            $query->whereHas('genres', fn (Builder $q) => $q->whereIn('slug', $genres));
        }

        if ($platforms !== []) {
            $query->whereHas('platforms', fn (Builder $q) => $q->whereIn('slug', $platforms));
        }

        match ($release) {
            GameReleaseFilter::Released => $query->reviewable(),
            GameReleaseFilter::Upcoming => $query->upcoming(),
            null => null,
        };

        $this->applySort($query, $sort);

        return $query->paginate($perPage);
    }

    /**
     * 공개 게임 상세. 없거나 공개되지 않았으면 404.
     */
    public function findPublished(int $id): Game
    {
        return Game::query()
            ->published()
            ->with(['genres', 'platforms', 'images', 'releaseDates.platform'])
            ->findOrFail($id);
    }

    /**
     * 웹 상세 주소(/games/{slug})용. 없거나 공개되지 않았으면 404.
     */
    public function findPublishedBySlug(string $slug): Game
    {
        return Game::query()
            ->published()
            ->with(['genres', 'platforms', 'images', 'releaseDates.platform'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * @param  Builder<Game>  $query
     */
    private function applySort(Builder $query, GameSort $sort): void
    {
        match ($sort) {
            GameSort::Popular => $query->orderByDesc('review_count'),
            GameSort::Rating => $query->orderByRaw('rating_avg DESC NULLS LAST')->orderByDesc('review_count'),
            GameSort::LatestRelease => $query->orderByRaw(
                '(SELECT MAX(release_date) FROM game_release_dates WHERE game_release_dates.game_id = games.id AND release_status = ?) DESC NULLS LAST',
                [ReleaseStatus::Released->value],
            ),
            GameSort::Title => $query->orderBy('title'),
        };

        // 같은 값이면 순서가 페이지마다 바뀌지 않도록 ID로 마무리한다
        $sort === GameSort::Title ? $query->orderBy('id') : $query->orderByDesc('id');
    }

    /**
     * 검색어의 %, _, \ 를 글자 그대로 찾도록 이스케이프한다 (PostgreSQL LIKE 기본 이스케이프 문자는 \)
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
