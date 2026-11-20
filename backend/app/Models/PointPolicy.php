<?php

namespace App\Models;

use App\Enums\PointType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 적립 사유별 금액·일일 한도 (포인트 정책 §3, ERD §7). 관리자가 바꿀 수 있다.
 *
 * @property int $id
 * @property PointType $action
 * @property int $points
 * @property int|null $daily_limit_count 하루 최대 횟수
 * @property int|null $daily_limit_points 하루 최대 포인트
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['points', 'daily_limit_count', 'daily_limit_points', 'is_active'])]
class PointPolicy extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PointType::class,
            'points' => 'integer',
            'daily_limit_count' => 'integer',
            'daily_limit_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
