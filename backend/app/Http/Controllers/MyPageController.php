<?php

namespace App\Http\Controllers;

use App\Enums\PointType;
use App\Http\Resources\Api\V1\FavoriteGameResource;
use App\Http\Resources\Api\V1\PointTransactionResource;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\GameReview;
use App\Models\PointTransaction;
use App\Models\Post;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\FavoriteService;
use App\Services\MyPageService;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 웹 마이페이지 (화면 설계 W-17): 대시보드, 내 리뷰, 내 글, 관심 게임, 포인트, 출석 탭.
 * 탭마다 주소가 따로 있고, 모든 탭이 머리 정보(`header`: 닉네임·잔액·등급)를 함께 받는다.
 * 앱 API와 같은 서비스를 쓴다. 주소창에서 잘못 바뀐 쿼리(type, month)는 오류 대신 기본값으로 보여 준다.
 */
class MyPageController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly MyPageService $mypage,
        private readonly FavoriteService $favorites,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * 대시보드 (REQ-601, 504)
     */
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $dashboard = $this->mypage->dashboard($user);

        $recent = PointTransaction::query()->where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();

        return $this->render($request, 'mypage/Dashboard', [
            'attendance' => $dashboard['attendance'],
            'counts' => $dashboard['counts'],
            'recentPoints' => $this->resources(PointTransactionResource::class, $recent->all(), $request),
        ]);
    }

    /**
     * 내 리뷰 (REQ-603, 313): 검토 중·숨김 리뷰도 상태·사유와 함께
     */
    public function reviews(Request $request): Response
    {
        $user = $this->user($request);
        $page = $this->mypage->reviews($user, self::PER_PAGE);

        return $this->render($request, 'mypage/Reviews', [
            'reviews' => $this->paginated($page, fn (GameReview $r) => (new ReviewResource($r))->forViewer($user)->resolve($request)),
        ]);
    }

    /**
     * 내 글 (REQ-603)
     */
    public function posts(Request $request): Response
    {
        $page = $this->mypage->posts($this->user($request), self::PER_PAGE);

        return $this->render($request, 'mypage/Posts', [
            'posts' => $this->paginated($page, fn (Post $p) => [
                'id' => $p->id,
                'board' => ['name' => $p->board->name, 'slug' => $p->board->slug, 'is_active' => $p->board->is_active],
                'title' => $p->title,
                'status' => $p->status->value,
                'hidden_reason' => $p->hidden_reason,
                'view_count' => $p->view_count,
                'like_count' => $p->like_count,
                'comment_count' => $p->comment_count,
                'created_at' => $p->created_at->toIso8601ZuluString(),
            ]),
        ]);
    }

    /**
     * 관심 게임 (REQ-604, 204)
     */
    public function favorites(Request $request): Response
    {
        $page = $this->favorites->listFor($this->user($request), self::PER_PAGE);

        return $this->render($request, 'mypage/Favorites', [
            'favorites' => [
                'items' => $this->resources(FavoriteGameResource::class, $page->items(), $request),
                'pagination' => $this->pagination($page),
            ],
        ]);
    }

    /**
     * 포인트 내역 (REQ-505): 사유로 거르기
     */
    public function points(Request $request): Response
    {
        $user = $this->user($request);
        $type = PointType::tryFrom($request->string('type')->value());
        $page = PointTransaction::query()
            ->where('user_id', $user->id)
            ->when($type, fn ($q, PointType $t) => $q->where('type', $t->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return $this->render($request, 'mypage/Points', [
            'transactions' => [
                'items' => $this->resources(PointTransactionResource::class, $page->items(), $request),
                'pagination' => $this->pagination($page),
            ],
            'type' => $type?->value,
            'types' => array_map(fn (PointType $t) => ['value' => $t->value, 'label' => $t->label()], PointType::cases()),
        ]);
    }

    /**
     * 출석 (REQ-501~503): 출석 버튼과 월 달력
     */
    public function attendance(Request $request): Response
    {
        $user = $this->user($request);
        $today = ServiceDate::today();
        $month = $request->string('month')->value();
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1
            ? (CarbonImmutable::createFromFormat('!Y-m', $month, ServiceDate::TIMEZONE) ?: $today)
            : $today;

        return $this->render($request, 'mypage/Attendance', [
            'status' => $this->attendance->today($user),
            'calendar' => $this->attendance->month($user, $month),
            'prevMonth' => $month->subMonthNoOverflow()->format('Y-m'),
            // 미래 달로는 넘기지 않는다
            'nextMonth' => $month->startOfMonth()->lt($today->startOfMonth()) ? $month->addMonthNoOverflow()->format('Y-m') : null,
        ]);
    }

    /**
     * 리소스 목록을 응답과 같은 순수 배열로 (안쪽 리소스까지 풀린다)
     *
     * @param  class-string<JsonResource>  $resource
     * @param  array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    private function resources(string $resource, array $items, Request $request): array
    {
        return $resource::collection($items)->toResponse($request)->getData(true)['data'];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function render(Request $request, string $component, array $props): Response
    {
        return Inertia::render($component, ['header' => $this->mypage->header($this->user($request)), ...$props]);
    }

    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $page
     * @param  callable(TItem): array<string, mixed>  $map
     * @return array{items: list<array<string, mixed>>, pagination: array{current_page: int, last_page: int, total: int}}
     */
    private function paginated(LengthAwarePaginator $page, callable $map): array
    {
        return ['items' => array_values(array_map($map, $page->items())), 'pagination' => $this->pagination($page)];
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @return array{current_page: int, last_page: int, total: int}
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
