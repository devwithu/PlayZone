<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\FavoriteGameResource;
use App\Models\Game;
use App\Models\User;
use App\Services\FavoriteService;
use App\Services\GameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 관심 게임·출시 알림 (REQ-604, REQ-204). 게임 상세·출시 일정·내 관심 게임 화면의 버튼이 부른다.
 * 앱 API(Api\V1\FavoriteController)와 같은 FavoriteService를 쓴다. 처리 후 보던 화면으로 돌아간다.
 */
class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favorites,
        private readonly GameService $games,
    ) {}

    /**
     * 내 관심 게임 (화면 설계 W-17 관심 게임 탭). 마이페이지는 3단계에서 탭으로 합친다.
     */
    public function index(Request $request): Response
    {
        $favorites = $this->favorites->listFor($this->user($request), 20);

        return Inertia::render('mypage/Favorites', [
            'favorites' => [
                'items' => FavoriteGameResource::collection($favorites->items())->toResponse($request)->getData(true)['data'],
                'pagination' => [
                    'current_page' => $favorites->currentPage(),
                    'last_page' => $favorites->lastPage(),
                    'total' => $favorites->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request, int $game): RedirectResponse
    {
        $this->favorites->add($this->user($request), $this->games->findPublished($game));

        return back();
    }

    public function destroy(Request $request, int $game): RedirectResponse
    {
        $this->favorites->remove($this->user($request), Game::query()->findOrFail($game));

        return back();
    }

    public function enableReleaseAlert(Request $request, int $game): RedirectResponse
    {
        $this->favorites->setReleaseAlert($this->user($request), $this->games->findPublished($game), true);

        return back();
    }

    public function disableReleaseAlert(Request $request, int $game): RedirectResponse
    {
        $this->favorites->setReleaseAlert($this->user($request), Game::query()->findOrFail($game), false);

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
