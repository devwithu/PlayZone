<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * 회원 등급 (ERD §8, 포인트 정책 §9). 누적 획득 포인트가 min_points 이상인 가장 높은 등급이 회원의 등급이다.
 * 이름·구간은 관리자가 바꾸고, 바꾸면 UserLevelService가 전체 회원 등급을 다시 계산한다.
 * min_points가 0인 기본 등급은 항상 있어야 한다.
 *
 * @property int $id
 * @property string $name
 * @property int $min_points 누적 획득 포인트 기준
 * @property string|null $icon_path
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'min_points', 'icon_path'])]
class UserLevel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_points' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // 아이콘을 바꾸거나 등급을 지우면 이전 파일도 지운다
        static::updated(function (UserLevel $level) {
            $old = $level->getOriginal('icon_path');
            if ($level->wasChanged('icon_path') && is_string($old) && $old !== '') {
                Storage::delete($old);
            }
        });
        static::deleted(function (UserLevel $level) {
            if ($level->icon_path !== null) {
                Storage::delete($level->icon_path);
            }
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isBase(): bool
    {
        return $this->min_points === 0;
    }

    public function iconUrl(): ?string
    {
        return $this->icon_path === null ? null : Storage::url($this->icon_path);
    }
}
