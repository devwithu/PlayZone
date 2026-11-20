<?php

namespace App\Http\Responses;

use App\Enums\ApiErrorCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

/**
 * API 공통 응답 형식 (api-spec §3).
 *
 * 성공: { success: true, message, data }
 * 목록: data = { items, pagination }
 * 실패: { success: false, code, message, errors } — 보통 직접 만들지 않고 ApiException을 던진다.
 */
final class ApiResponse
{
    private const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * @param  JsonResource|array<array-key, mixed>|null  $data  null이면 빈 객체 {}로 응답한다
     */
    public static function success(JsonResource|array|null $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data ?? new stdClass,
        ], $status, [], self::JSON_OPTIONS);
    }

    /**
     * 생성 성공 (201).
     *
     * @param  JsonResource|array<array-key, mixed>|null  $data
     */
    public static function created(JsonResource|array|null $data = null, string $message = ''): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * 페이지 목록. $resource를 주면 각 항목을 그 API Resource로 변환한다.
     *
     * @param  LengthAwarePaginator<array-key, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource
     */
    public static function paginated(LengthAwarePaginator $paginator, ?string $resource = null, string $message = ''): JsonResponse
    {
        $items = $resource === null
            ? array_values($paginator->items())
            : $resource::collection($paginator->items());

        return self::success([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ], $message);
    }

    /**
     * @param  array<string, mixed>  $errors
     * @param  array<string, string>  $headers
     */
    public static function error(
        ApiErrorCode $code,
        ?string $message = null,
        array $errors = [],
        ?int $status = null,
        array $headers = [],
    ): JsonResponse {
        return new JsonResponse([
            'success' => false,
            'code' => $code->value,
            'message' => $message ?? $code->defaultMessage(),
            'errors' => $errors === [] ? new stdClass : $errors,
        ], $status ?? $code->status(), $headers, self::JSON_OPTIONS);
    }
}
