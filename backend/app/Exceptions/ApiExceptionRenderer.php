<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * /api/* 요청의 모든 예외를 공통 오류 형식(api-spec §3, §5)으로 바꾼다.
 *
 * 메시지는 ApiException이 준 것만 그대로 쓰고, 프레임워크 예외(404, 403 등)는
 * 영어 기본 문구가 나가지 않도록 오류 코드의 기본 문구로 바꾼다.
 * 특정 문구가 필요하면 ApiException을 던진다.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ApiException => ApiResponse::error($e->errorCode, $e->getMessage(), $e->errors, $e->getStatusCode(), $e->getHeaders()),
            $e instanceof ValidationException => ApiResponse::error(ApiErrorCode::ValidationFailed, errors: $e->errors()),
            $e instanceof AuthenticationException => ApiResponse::error(ApiErrorCode::Unauthenticated),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            // 500: 상세 내용은 응답하지 않는다 (api-spec §4). 예외는 로그에 남는다.
            default => ApiResponse::error(ApiErrorCode::ServerError),
        };
    }

    private function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        $code = match (true) {
            $status === 401 => ApiErrorCode::Unauthenticated,
            $status === 403 => ApiErrorCode::Forbidden,
            $status === 404 => ApiErrorCode::NotFound,
            $status === 405 => ApiErrorCode::MethodNotAllowed,
            $status === 429 => ApiErrorCode::RateLimited,
            $status >= 500 => ApiErrorCode::ServerError,
            default => ApiErrorCode::BadRequest,
        };

        // Retry-After(429), Allow(405) 같은 헤더는 유지한다.
        return ApiResponse::error($code, status: $status, headers: $e->getHeaders());
    }
}
