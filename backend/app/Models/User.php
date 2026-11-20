<?php

namespace App\Models;

use App\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string|null $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password 이메일·비밀번호 가입 확장 대비, 지금은 항상 NULL (ADR-003 §8)
 * @property string|null $nickname
 * @property UserStatus $status
 * @property int $point_balance
 * @property int $lifetime_points
 * @property CarbonImmutable|null $signup_completed_at
 * @property CarbonImmutable|null $terms_agreed_at
 * @property CarbonImmutable|null $age_confirmed_at
 * @property CarbonImmutable|null $marketing_agreed_at
 * @property CarbonImmutable|null $withdrawn_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['email', 'email_verified_at', 'nickname'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'point_balance' => 0,
        'lifetime_points' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'point_balance' => 'integer',
            'lifetime_points' => 'integer',
            'signup_completed_at' => 'datetime',
            'terms_agreed_at' => 'datetime',
            'age_confirmed_at' => 'datetime',
            'marketing_agreed_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->roles->whereIn('slug', $slugs)->isNotEmpty();
    }

    public function isPending(): bool
    {
        return $this->status === UserStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * 남아 있는 로그인 수단 수. 이메일 가입을 추가하면 비밀번호도 센다 (ADR-003 §8).
     */
    public function loginMethodCount(): int
    {
        return $this->socialAccounts()->count() + ($this->password !== null ? 1 : 0);
    }
}
