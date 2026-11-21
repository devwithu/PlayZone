<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 출석 기록 (ERD §7). AttendanceService만 추가한다.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $attendance_date KST 서비스 일자
 * @property int $streak_days 연속 출석 일수 (1~30)
 * @property CarbonImmutable $created_at
 */
class Attendance extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'immutable_date',
            'streak_days' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
