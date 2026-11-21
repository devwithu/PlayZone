<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use App\Services\FavoriteService;
use App\Services\GameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 웹 관심 게임·출시 알림 버튼 (REQ-604, REQ-204). 게임 상세·출시 일정·마이페이지 관심 게임 탭의 버튼이 부른다.
 * 목록 화면은 MyPageController::favorites.
 * 앱 API(Api\V1\FavoriteController)와 같은 FavoriteService를 쓴다. 처리 후 보던 화면으로 돌아간다.
 */
class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favorites,
        private readonly GameService $games,
    ) {}

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
