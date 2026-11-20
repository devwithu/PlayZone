<?php

namespace App\Jobs;

use App\Services\SocialAuth\AppleTokenClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 탈퇴한 회원의 Apple refresh token 폐기 (ADR-003 §5).
 *
 * 탈퇴 트랜잭션이 커밋된 뒤 실행하고, Apple 호출이 실패해도 탈퇴는 되돌리지 않는다.
 * 토큰이 큐(Redis)에 평문으로 남지 않도록 작업 내용을 암호화한다.
 */
class RevokeAppleToken implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> 재시도 간격 (초) */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $refreshToken,
        public readonly string $clientId,
    ) {}

    public function handle(AppleTokenClient $apple): void
    {
        $apple->revokeRefreshToken($this->refreshToken, $this->clientId);
    }
}
