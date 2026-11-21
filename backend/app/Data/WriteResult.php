<?php

namespace App\Data;

use Illuminate\Database\Eloquent\Model;

/**
 * 글(리뷰·댓글) 작성 결과와 지급 포인트. API 응답의 `data.point`로 point를 내보낸다.
 *
 * @template TModel of Model
 */
final readonly class WriteResult
{
    /**
     * @param  TModel  $model
     */
    public function __construct(
        public Model $model,
        public PointResult $point,
    ) {}
}
