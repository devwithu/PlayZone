<?php

namespace App\Http\Requests;

use App\Http\Requests\Api\V1\GameIndexRequest;
use Illuminate\Support\Facades\Validator;

/**
 * 웹 게임 목록 쿼리 (화면 설계 W-04). 앱 API와 같은 규칙을 쓰되, 주소창에서 바뀐 잘못된 값은
 * 오류 화면 대신 조용히 무시한다. 검색어가 1자면 화면에 "2자 이상 입력해 주세요"를 보여 준다.
 */
class GameBrowseRequest extends GameIndexRequest
{
    private ?string $keywordError = null;

    protected function prepareForValidation(): void
    {
        foreach (parent::rules() as $field => $rules) {
            if (! $this->has($field)) {
                continue;
            }

            $validator = Validator::make([$field => $this->input($field)], [$field => $rules]);
            if ($validator->fails()) {
                if ($field === 'q') {
                    $this->keywordError = '2자 이상 입력해 주세요.';
                }
                $this->merge([$field => null]);
            }
        }
    }

    public function keywordError(): ?string
    {
        return $this->keywordError;
    }
}
