<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameBrowseRequest;
use App\Http\Resources\Api\V1\GameDetailResource;
use App\Http\Resources\Api\V1\GameListResource;
use App\Http\Resources\Api\V1\TaxonomyResource;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\User;
use App\Services\FavoriteService;
use App\Services\GameService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 게임 목록·상세 (화면 설계 W-04, W-05). SSR로 첫 HTML에 제목·메타 태그가 들어간다 (WEB-003).
 * 앱 API(Api\V1\GameController)와 같은 GameService와 응답 형태(Resource)를 쓴다.
 */
class GameController extends Controller
{
    public function __construct(
        private readonly GameService $games,
        private readonly FavoriteService $favorites,
    ) {}

    public function index(GameBrowseRequest $request): Response
    {
        $games = $this->games->search(
            keyword: $request->keyword(),
            genres: $request->slugs('genre'),
            platforms: $request->slugs('platform'),
            release: $request->releaseFilter(),
            sort: $request->sort(),
            perPage: 24,
        );

        return Inertia::render('games/Index', [
            'games' => [
                'items' => $this->props(GameListResource::collection($games->items()), $request),
                'pagination' => [
                    'current_page' => $games->currentPage(),
                    'last_page' => $games->lastPage(),
                    'total' => $games->total(),
                ],
            ],
            'filters' => [
                'q' => $request->keyword() ?? '',
                'genre' => $request->slugs('genre'),
                'platform' => $request->slugs('platform'),
                'release_status' => $request->releaseFilter()->value ?? '',
                'sort' => $request->sort()->value,
            ],
            'keywordError' => $request->keywordError(),
            'genres' => $this->props(TaxonomyResource::collection(Genre::query()->ordered()->get()), $request),
            'platforms' => $this->props(TaxonomyResource::collection(Platform::query()->ordered()->get()), $request),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $game = $this->games->findPublishedBySlug($slug);
        /** @var User|null $user */
        $user = $request->user();
        $detail = $this->props((new GameDetailResource($game))->forViewer($this->favorites->stateFor($user, $game)), $request);

        return Inertia::render('games/Show', [
            'game' => $detail,
            // 검색엔진·공유 미리보기용 (첫 HTML에 들어감)
            'meta' => [
                'description' => Str::limit(
                    trim(preg_replace('/\s+/u', ' ', $game->description ?? '') ?? '') ?: "{$game->title} 게임 정보, 출시일, 유저 평가",
                    150,
                ),
                'image' => $detail['cover_url'],
                'url' => route('games.show', $game->slug),
            ],
        ]);
    }

    /**
     * Resource를 앱 API 응답의 `data`와 똑같은 배열로 바꾼다. resolve()만 쓰면 안쪽 컬렉션
     * (장르, 출시일 등)이 Inertia에서 `{data: [...]}`로 한 번 더 감싸진다.
     *
     * @return array<array-key, mixed>
     */
    private function props(JsonResource $resource, Request $request): array
    {
        /** @var array{data: array<array-key, mixed>} $payload */
        $payload = $resource->toResponse($request)->getData(true);

        return $payload['data'];
    }
}
