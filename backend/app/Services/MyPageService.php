<?php

namespace App\Services;

use App\Enums\ReviewStatus;
use App\Models\FavoriteGame;
use App\Models\GameReview;
use App\Models\Post;
use App\Models\User;
use App\Support\ServiceDate;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 마이페이지 (REQ-601, 603, 313, 504, 화면 설계 W-17). 웹과 앱 API가 함께 쓴다.
 *
 * - 내 리뷰·내 글에는 검토 중(blinded)·숨김(hidden) 글도 상태·사유와 함께 넣는다. 삭제한 글은 뺀다.
 * - 작성 수도 삭제한 글만 빼고 센다(검토 중·숨김 포함).
 */
class MyPageService
{
    public function __construct(
        private readonly UserLevelService $levels,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * 마이페이지 머리: 닉네임, 잔액, 누적, 등급과 다음 등급까지 남은 포인트
     *
     * @return array<string, mixed>
     */
    public function header(User $user): array
    {
        $user->loadMissing(['level', 'profile']);

        return [
            'nickname' => $user->nickname,
            'introduction' => $user->profile?->introduction,
            'profile_image_url' => $user->profileImageUrl(),
            'point_balance' => $user->point_balance,
            'lifetime_points' => $user->lifetime_points,
            ...$this->levels->summary($user),
        ];
    }

    /**
     * 대시보드 (REQ-601): 머리 정보 + 이번 달 출석, 연속 출석, 작성 수, 관심 게임 수
     *
     * @return array<string, mixed>
     */
    public function dashboard(User $user): array
    {
        $today = $this->attendance->today($user);
        $month = $this->attendance->month($user, ServiceDate::today());

        return [
            ...$this->header($user),
            'attendance' => [
                'attended_today' => $today['attended'],
                'streak_days' => $today['streak_days'],
                'month_count' => $month['count'],
            ],
            'counts' => [
                'reviews' => GameReview::query()->where('user_id', $user->id)->notDeleted()->count(),
                'posts' => Post::query()->where('user_id', $user->id)->where('status', '<>', ReviewStatus::Deleted)->count(),
                'favorites' => FavoriteGame::query()->where('user_id', $user->id)->count(),
            ],
        ];
    }

    /**
     * 내 리뷰 (REQ-603, 313). 최근 작성 순.
     *
     * @return LengthAwarePaginator<int, GameReview>
     */
    public function reviews(User $user, int $perPage): LengthAwarePaginator
    {
        return GameReview::query()
            ->with([...ReviewService::WITH, 'game'])
            ->where('user_id', $user->id)
            ->notDeleted()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * 내 게시글 (REQ-603). 최근 작성 순. 꺼진 게시판의 글도 내 목록에는 남는다.
     *
     * @return LengthAwarePaginator<int, Post>
     */
    public function posts(User $user, int $perPage): LengthAwarePaginator
    {
        return Post::query()
            ->with(['board', 'user.level'])
            ->where('user_id', $user->id)
            ->where('status', '<>', ReviewStatus::Deleted)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
