<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 포인트 거래 내역 한 줄 (api-spec §7.6, 화면 설계 W-17 포인트 탭).
 *
 * @mixin PointTransaction
 */
class PointTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            /** 거래 사유 코드 (포인트 정책 §3, §4) */
            'type' => $this->type,
            /** 화면 표시용 사유. 예: `출석`, `리뷰 작성` */
            'type_label' => $this->type->label(),
            /** 적립은 양수, 차감은 음수 */
            'amount' => $this->amount,
            /** 거래 직후 잔액 */
            'balance_after' => $this->balance_after,
            /** 관리자 지급·차감 사유, 회수 부족분 등 */
            'description' => $this->description,
            /** 거래 시각 (UTC) */
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
