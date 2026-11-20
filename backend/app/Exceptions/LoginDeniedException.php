<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Carbon\CarbonInterface;

/**
 * 소셜 로그인은 성공했지만 서비스 이용을 막아야 하는 경우.
 * 웹은 컨트롤러에서 잡아 로그인 화면에 메시지를 보여 주고, API는 그대로 오류 응답(api-spec §5)이 된다.
 */
class LoginDeniedException extends ApiException
{
    public static function rejoinRestricted(CarbonInterface $availableAt): self
    {
        return new self(
            ApiErrorCode::RejoinRestricted,
            '탈퇴 후 30일 동안은 같은 계정으로 다시 가입할 수 없습니다. '
                .$availableAt->timezone('Asia/Seoul')->format('Y년 n월 j일').'부터 가입할 수 있습니다.',
        );
    }

    public static function suspended(): self
    {
        return new self(ApiErrorCode::AccountSuspended, '이용이 정지된 계정입니다. 자세한 내용은 고객센터로 문의해 주세요.');
    }
}
