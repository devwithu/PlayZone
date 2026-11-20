<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * 서비스가 업무 규칙 위반을 알릴 때 던지는 예외 (api-spec §5).
 *
 * API 요청에서는 { success: false, code, message, errors } 형식으로 응답한다.
 * HttpException을 상속하므로 웹 요청에서는 같은 상태 코드의 오류 페이지가 된다.
 * 웹 화면에서 다르게 보여 줘야 하면 컨트롤러에서 잡아서 처리한다.
 */
class ApiException extends HttpException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        ?string $message = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode->status(), $message ?? $errorCode->defaultMessage(), $previous);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    public static function of(ApiErrorCode $errorCode, ?string $message = null, array $errors = []): self
    {
        return new self($errorCode, $message, $errors);
    }
}
