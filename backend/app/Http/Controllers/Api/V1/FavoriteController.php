<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PaginatedRequest;
use App\Http\Resources\Api\V1\FavoriteGameResource;
use App\Http\Responses\ApiResponse;
use App\Models\Game;
use App\Models\User;
use App\Services\FavoriteService;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 관심 게임·출시 알림 (api-spec §7.4, §7.7). 가입을 마친 회원만 쓴다.
 *
 * 등록·켜기는 공개 게임에만 할 수 있다. 해제·끄기는 숨겨진 게임에도 할 수 있다.
 * 모두 멱등이고, 결과 상태 `{is_favorite, release_alert, favorite_count}`를 돌려준다.
 */
class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favorites,
        private readonly GameService $games,
    ) {}

    /**
     * 내 관심 게임
     *
     * 최근 등록 순. 공개되지 않은 게임은 빠진다.
     */
    public function index(PaginatedRequest $request): JsonResponse
    {
        $favorites = $this->favorites->listFor($this->user($request), $request->perPage());

        return ApiResponse::paginated($favorites->withQueryString(), FavoriteGameResource::class);
    }

    /**
     * 관심 게임 등록
     *
     * 새로 등록하면 출시 알림도 켜진다. 이미 등록된 게임이면 그대로 성공한다.
     */
    public function store(Request $request, int $game): JsonResponse
    {
        $state = $this->favorites->add($this->user($request), $this->games->findPublished($game));

        return ApiResponse::success($state->toArray());
    }

    /**
     * 관심 게임 해제
     *
     * 출시 알림도 함께 꺼진다. 등록되지 않은 게임이어도 성공한다.
     */
    public function destroy(Request $request, int $game): JsonResponse
    {
        $state = $this->favorites->remove($this->user($request), Game::query()->findOrFail($game));

        return ApiResponse::success($state->toArray());
    }

    /**
     * 출시 알림 켜기
     *
     * 관심 게임이 아니면 함께 등록한다.
     */
    public function enableReleaseAlert(Request $request, int $game): JsonResponse
    {
        $state = $this->favorites->setReleaseAlert($this->user($request), $this->games->findPublished($game), true);

        return ApiResponse::success($state->toArray());
    }

    /**
     * 출시 알림 끄기
     *
     * 관심 게임은 유지한다.
     */
    public function disableReleaseAlert(Request $request, int $game): JsonResponse
    {
        $state = $this->favorites->setReleaseAlert($this->user($request), Game::query()->findOrFail($game), false);

        return ApiResponse::success($state->toArray());
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
