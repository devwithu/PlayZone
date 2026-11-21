<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RankingType;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\RankingService;
use App\Support\ServiceDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 랭킹 (api-spec §7.6). 로그인 없이 볼 수 있고, Bearer 토큰을 보내면 `me`(내 순위)를 채운다.
 */
class RankingController extends Controller
{
    public function __construct(private readonly RankingService $rankings) {}

    /**
     * 랭킹
     *
     * `type`: `points_weekly`(주간 포인트, 기본), `reviews_monthly`(월간 리뷰). `date`: 기간 안의 아무 날짜(KST, 없으면 이번 기간, 미래면 이번 기간).
     * 상위 100위까지 `items`. 같은 점수는 같은 순위. 매시 정각에 집계하며 마지막 집계 시각은 `updated_at`.
     * 이전·다음 기간은 `prev_date`, `next_date`(없으면 null)로 다시 부른다.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', Rule::enum(RankingType::class)],
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $type = RankingType::tryFrom((string) ($validated['type'] ?? '')) ?? RankingType::PointsWeekly;
        $date = isset($validated['date']) ? CarbonImmutable::parse((string) $validated['date'], ServiceDate::TIMEZONE) : null;

        /** @var User|null $viewer */
        $viewer = $request->user('sanctum');

        return ApiResponse::success($this->rankings->board($type, $date, $viewer));
    }
}
