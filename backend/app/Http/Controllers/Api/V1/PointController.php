<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PointHistoryRequest;
use App\Http\Resources\Api\V1\PointTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\UserLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 포인트 잔액·내역 (api-spec §7.6). 가입을 마친 회원만 쓴다.
 */
class PointController extends Controller
{
    public function __construct(private readonly UserLevelService $levels) {}

    /**
     * 포인트 잔액
     *
     * 잔액, 누적 획득 포인트, 현재 등급과 다음 등급까지 남은 포인트(`next_level.points_needed`, 최고 등급이면 `next_level` null).
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return ApiResponse::success([
            /** 현재 사용 가능한 포인트 */
            'point_balance' => $user->point_balance,
            /** 지금까지 적립한 포인트 (사용해도 줄지 않음, 회수하면 줄어듦). 등급 기준 */
            'lifetime_points' => $user->lifetime_points,
            ...$this->levels->summary($user),
        ]);
    }

    /**
     * 포인트 거래 내역
     *
     * 최근 거래 순. `type`으로 사유를 거른다.
     */
    public function history(PointHistoryRequest $request): JsonResponse
    {
        $transactions = PointTransaction::query()
            ->where('user_id', $this->user($request)->id)
            ->when($request->type(), fn ($q, $type) => $q->where('type', $type->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->perPage());

        return ApiResponse::paginated($transactions->withQueryString(), PointTransactionResource::class);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
