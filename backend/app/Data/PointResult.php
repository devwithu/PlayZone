<?php

namespace App\Data;

use App\Enums\PointType;
use App\Models\PointTransaction;

/**
 * PointService 처리 결과. API 응답의 `data.point`(api-spec §3)로 그대로 내보낸다.
 */
final readonly class PointResult
{
    public function __construct(
        public PointType $type,
        /** 이번에 바뀐 포인트 (적립 양수, 차감 음수). 지급하지 않았으면 0 */
        public int $earned,
        /** 처리 후 잔액 */
        public int $balance,
        /** 일일 한도에 걸려 지급하지 않았거나 줄여서 지급했는지 */
        public bool $limited = false,
        /** 같은 중복 방지 키로 이미 처리된 요청이었는지 (재시도) */
        public bool $alreadyProcessed = false,
        public ?PointTransaction $transaction = null,
    ) {}

    /**
     * @return array{earned: int, balance: int, type: string, limited: bool}
     */
    public function toArray(): array
    {
        return [
            'earned' => $this->earned,
            'balance' => $this->balance,
            'type' => $this->type->value,
            'limited' => $this->limited,
        ];
    }
}
