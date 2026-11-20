<?php

namespace App\Support\OpenApi;

use App\Enums\ApiErrorCode;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Scramble은 오류 응답을 Laravel 기본 형식({ message, errors })으로 문서화한다.
 * 실제 API는 ApiExceptionRenderer가 { success: false, code, message, errors }로 응답하므로,
 * 생성된 문서의 4xx·5xx 응답 본문을 그 형식으로 바꾼다 (api-spec §3, §5).
 */
final class ApiErrorResponseTransformer implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($document->components->responses as $response) {
            if ($response instanceof Response) {
                $this->rewrite($response);
            }
        }

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->responses ?? [] as $response) {
                    if ($response instanceof Response) {
                        $this->rewrite($response);
                    }
                }
            }
        }
    }

    private function rewrite(Response $response): void
    {
        $status = (int) $response->code;

        if ($status < 400) {
            return;
        }

        $codes = array_values(array_map(
            fn (ApiErrorCode $code): string => $code->value,
            array_filter(ApiErrorCode::cases(), fn (ApiErrorCode $code): bool => $code->status() === $status),
        ));

        $codeType = (new StringType)->setDescription('앱이 분기에 쓰는 고정 오류 코드 (api-spec §5)');
        if ($codes !== []) {
            $codeType->enum($codes);
        }

        $errorsType = (new ObjectType)->setDescription('추가 정보. 입력값 오류면 필드별 메시지 목록');
        if ($status === 422) {
            $errorsType->additionalProperties((new ArrayType)->setItems(new StringType));
        }

        $body = (new ObjectType)
            ->addProperty('success', (new BooleanType)->const(false))
            ->addProperty('code', $codeType)
            ->addProperty('message', (new StringType)->setDescription('화면 표시용 메시지 (바뀔 수 있음)'))
            ->addProperty('errors', $errorsType)
            ->setRequired(['success', 'code', 'message', 'errors']);

        $response->setContent('application/json', Schema::fromType($body));
    }
}
