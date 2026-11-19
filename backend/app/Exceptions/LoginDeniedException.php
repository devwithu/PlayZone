<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * 소셜 로그인은 성공했지만 서비스 이용을 막아야 하는 경우.
 * $code는 API 오류 코드(api-spec §5)와 같은 문자열이다.
 */
class LoginDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function rejoinRestricted(CarbonInterface $availableAt): self
    {
        return new self(
            'REJOIN_RESTRICTED',
            '탈퇴 후 30일 동안은 같은 계정으로 다시 가입할 수 없습니다. '
                .$availableAt->timezone('Asia/Seoul')->format('Y년 n월 j일').'부터 가입할 수 있습니다.',
        );
    }

    public static function suspended(): self
    {
        return new self('ACCOUNT_SUSPENDED', '이용이 정지된 계정입니다. 자세한 내용은 고객센터로 문의해 주세요.');
    }
}
