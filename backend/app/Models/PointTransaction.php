<?php

namespace App\Models;

use App\Enums\PointType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * 포인트 거래 원장 (포인트 정책 §2, ERD §7). PointService만 추가하고, 수정·삭제하지 않는다.
 * 잘못 지급했으면 반대 금액의 거래(revoke)를 새로 추가한다.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount 적립은 양수, 차감은 음수
 * @property int $balance_after 거래 직후 잔액
 * @property PointType $type
 * @property string|null $reference_type review, post, comment, event, point_transaction …
 * @property int|null $reference_id
 * @property string $idempotency_key 중복 지급 방지 (포인트 정책 §5)
 * @property string|null $description
 * @property int|null $admin_id
 * @property CarbonImmutable $service_date KST 서비스 일자
 * @property CarbonImmutable $created_at
 */
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    /** PointService만 채운다 */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'type' => PointType::class,
            'reference_id' => 'integer',
            'service_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('포인트 원장은 수정할 수 없습니다. 반대 거래를 추가하세요.'));
        static::deleting(fn () => throw new LogicException('포인트 원장은 삭제할 수 없습니다. 반대 거래를 추가하세요.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
