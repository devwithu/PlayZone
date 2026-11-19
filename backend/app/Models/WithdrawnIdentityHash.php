<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 탈퇴 후 30일 재가입 제한 (ERD §12, ADR-003 §5).
 * 개인정보를 남기지 않도록 로그인 수단 식별자의 HMAC만 보관한다.
 *
 * @property int $id
 * @property string $identity_hash
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['identity_hash', 'expires_at'])]
class WithdrawnIdentityHash extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /**
     * @param  string  $method  로그인 수단 (google, apple, 이메일 가입 추가 시 password)
     * @param  string  $identifier  제공자 사용자 ID, 이메일 가입이면 소문자 이메일
     */
    public static function hashFor(string $method, string $identifier): string
    {
        return hash_hmac('sha256', $method.':'.$identifier, (string) config('app.key'));
    }
}
