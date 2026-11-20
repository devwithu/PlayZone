<?php

namespace App\Services\SocialAuth;

use App\Enums\LoginProvider;

/**
 * 검증을 마친 소셜 ID 토큰에서 꺼낸 회원 식별 정보.
 * 앱이 따로 보낸 값이 아니라 토큰 안의 값만 담는다 (ADR-003 §3).
 */
final readonly class SocialIdentity
{
    public function __construct(
        public LoginProvider $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        /** 토큰의 aud. Apple authorization_code 교환 때 client_id로 쓴다 */
        public string $audience,
    ) {}
}
