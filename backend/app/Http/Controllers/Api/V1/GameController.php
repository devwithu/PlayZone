<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GameIndexRequest;
use App\Http\Resources\Api\V1\GameDetailResource;
use App\Http\Resources\Api\V1\GameListResource;
use App\Http\Resources\Api\V1\TaxonomyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\RatingCategory;
use App\Models\User;
use App\Services\FavoriteService;
use App\Services\GameRatingService;
use App\Services\GameService;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 게임 (api-spec §7.2). 로그인 없이 호출할 수 있고, 공개 게임만 보인다.
 */
class GameController extends Controller
{
    public function __construct(
        private readonly GameService $games,
        private readonly FavoriteService $favorites,
        private readonly ReviewService $reviews,
        private readonly GameRatingService $ratings,
    ) {}

    /**
     * 게임 목록·검색
     *
     * 검색어(`q`, 제목 중간 일치), 장르·플랫폼(slug, 쉼표로 여러 개), 출시 여부로 거르고 `sort`로 정렬한다.
     */
    public function index(GameIndexRequest $request): JsonResponse
    {
        $games = $this->games->search(
            keyword: $request->keyword(),
            genres: $request->slugs('genre'),
            platforms: $request->slugs('platform'),
            release: $request->releaseFilter(),
            sort: $request->sort(),
            perPage: $request->perPage(),
        );

        return ApiResponse::paginated($games->withQueryString(), GameListResource::class);
    }

    /**
     * 게임 상세
     *
     * 기본 정보, 이미지, 플랫폼별 출시일, 리뷰 수·평균. Bearer 토큰을 보내면 `my_review_id`, `is_favorite`, `release_alert`를 채운다(비회원은 null). 없거나 공개되지 않은 게임은 `404 NOT_FOUND`.
     */
    public function show(Request $request, int $game): JsonResponse
    {
        $detail = $this->games->findPublished($game);
        // 공개 API라 인증 미들웨어가 없다. 토큰을 보냈으면 그 회원의 관심·출시 알림 상태를 채운다
        /** @var User|null $user */
        $user = $request->user('sanctum');

        return ApiResponse::success(
            (new GameDetailResource($detail))
                ->forViewer($this->favorites->stateFor($user, $detail))
                ->withMyReview($this->reviews->myReviewId($user, $detail)),
        );
    }

    /**
     * 점수 분포·세부 항목 평균
     *
     * 공개 리뷰의 종합 점수 1~10별 리뷰 수(`distribution`)와 세부 항목 평균(`categories`).
     * 공개 리뷰가 3개 미만이면 `rating_avg`와 항목별 `avg_score`는 null("평가 부족").
     */
    public function ratingSummary(int $game): JsonResponse
    {
        return ApiResponse::success($this->ratings->summary($this->games->findPublished($game)));
    }

    /**
     * 세부 평가 항목 목록
     *
     * 리뷰 작성의 `ratings` 키(slug)와 화면 표시 이름. 사용 중인 항목만, 표시 순서대로.
     */
    public function ratingCategories(): JsonResponse
    {
        return ApiResponse::success(
            RatingCategory::query()->active()->ordered()->get()
                ->map(fn (RatingCategory $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])
                ->values()
                ->all(),
        );
    }

    /**
     * 장르 목록
     *
     * 게임 목록 필터용. `slug`를 `GET /games?genre=`에 쓴다.
     */
    public function genres(): JsonResponse
    {
        return ApiResponse::success(TaxonomyResource::collection(Genre::query()->ordered()->get()));
    }

    /**
     * 플랫폼 목록
     *
     * 게임 목록 필터용. `slug`를 `GET /games?platform=`에 쓴다.
     */
    public function platforms(): JsonResponse
    {
        return ApiResponse::success(TaxonomyResource::collection(Platform::query()->ordered()->get()));
    }
}
