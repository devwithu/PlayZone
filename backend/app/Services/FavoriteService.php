<?php

namespace App\Services;

use App\Data\FavoriteState;
use App\Models\FavoriteGame;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 관심 게임·출시 알림 (api-spec §7.4, §7.7, ERD §9). 앱 API와 웹 화면이 함께 쓴다.
 *
 * games.favorite_count는 favorite_games의 집계 캐시다. 등록·해제와 같은 트랜잭션에서 실제로 추가·삭제된
 * 행 수만큼만 바꾸므로, 같은 요청이 동시에 와도(UNIQUE (user_id, game_id)) 두 번 세지 않는다.
 * 모든 요청은 결과 상태를 돌려주는 멱등 동작이다(이미 등록된 게임을 다시 등록해도 성공).
 */
class FavoriteService
{
    /**
     * 관심 게임 등록. 새로 등록하면 출시 알림은 켠 상태(ERD 기본값)다. 이미 있으면 그대로 둔다.
     */
    public function add(User $user, Game $game): FavoriteState
    {
        DB::transaction(fn () => $this->insert($user, $game));

        return $this->state($user, $game);
    }

    /**
     * 관심 게임 해제. 출시 알림도 함께 사라진다.
     */
    public function remove(User $user, Game $game): FavoriteState
    {
        DB::transaction(function () use ($user, $game) {
            $deleted = FavoriteGame::query()->where('user_id', $user->id)->where('game_id', $game->id)->delete();

            if ($deleted > 0) {
                Game::query()->whereKey($game->id)->decrement('favorite_count', $deleted);
            }
        });

        return $this->state($user, $game);
    }

    /**
     * 출시 알림 켜기·끄기. 켤 때 관심 게임이 아니면 함께 등록하고, 꺼도 관심 게임은 유지한다 (api-spec §7.4).
     */
    public function setReleaseAlert(User $user, Game $game, bool $on): FavoriteState
    {
        DB::transaction(function () use ($user, $game, $on) {
            if ($on && $this->insert($user, $game)) {
                return;
            }

            FavoriteGame::query()
                ->where('user_id', $user->id)
                ->where('game_id', $game->id)
                ->update(['release_alert' => $on, 'updated_at' => now()]);
        });

        return $this->state($user, $game);
    }

    /**
     * 회원의 관심 게임을 모두 지운다 (탈퇴). 호출하는 쪽 트랜잭션 안에서 부른다.
     */
    public function removeAllFor(User $user): void
    {
        $gameIds = FavoriteGame::query()->where('user_id', $user->id)->pluck('game_id');

        if ($gameIds->isEmpty()) {
            return;
        }

        FavoriteGame::query()->where('user_id', $user->id)->delete();
        Game::query()->whereKey($gameIds->all())->decrement('favorite_count');
    }

    /**
     * 로그인 회원의 상태. 비회원이면 null.
     */
    public function stateFor(?User $user, Game $game): ?FavoriteState
    {
        return $user === null ? null : $this->state($user, $game);
    }

    /**
     * 여러 게임의 출시 알림 상태 (출시 일정 화면의 🔔). 관심 게임이 아니면 false.
     *
     * @param  list<int>  $gameIds
     * @return array<int, bool> game_id => release_alert
     */
    public function releaseAlerts(User $user, array $gameIds): array
    {
        $alerts = FavoriteGame::query()
            ->where('user_id', $user->id)
            ->whereIn('game_id', $gameIds)
            ->pluck('release_alert', 'game_id');

        return collect($gameIds)
            ->mapWithKeys(fn (int $id) => [$id => (bool) ($alerts[$id] ?? false)])
            ->all();
    }

    /**
     * 내 관심 게임 (최근 등록 순). 공개되지 않은 게임은 목록에서 뺀다(등록은 남겨 둔다).
     *
     * @return LengthAwarePaginator<int, FavoriteGame>
     */
    public function listFor(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return FavoriteGame::query()
            ->where('user_id', $user->id)
            ->whereHas('game', fn ($q) => $q->published())
            ->with(['game.cover', 'game.genres', 'game.platforms', 'game.releaseDates'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return bool 새로 등록했는지
     */
    private function insert(User $user, Game $game): bool
    {
        $now = now();
        // ON CONFLICT DO NOTHING: 이미 있으면 0행
        $inserted = DB::table('favorite_games')->insertOrIgnore([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'release_alert' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted > 0) {
            Game::query()->whereKey($game->id)->increment('favorite_count');
        }

        return $inserted > 0;
    }

    private function state(User $user, Game $game): FavoriteState
    {
        $favorite = FavoriteGame::query()->where('user_id', $user->id)->where('game_id', $game->id)->first();

        return new FavoriteState(
            isFavorite: $favorite !== null,
            releaseAlert: $favorite !== null && $favorite->release_alert,
            favoriteCount: (int) Game::query()->whereKey($game->id)->value('favorite_count'),
        );
    }
}
