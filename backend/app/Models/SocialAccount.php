<?php

namespace App\Models;

use App\Enums\LoginProvider;
use Carbon\CarbonImmutable;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property LoginProvider $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property string|null $refresh_token Apple 탈퇴 시 토큰 폐기용, 암호화 저장 (ADR-003 §5)
 * @property string|null $refresh_token_client_id refresh token을 발급받은 client_id (웹 Services ID 또는 앱 번들 ID)
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $last_login_at
 */
#[Fillable(['provider', 'provider_user_id', 'email', 'refresh_token', 'refresh_token_client_id', 'last_login_at'])]
#[Hidden(['refresh_token'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => LoginProvider::class,
            'refresh_token' => 'encrypted',
            'last_login_at' => 'datetime',
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
