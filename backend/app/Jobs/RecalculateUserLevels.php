<?php

namespace App\Jobs;

use App\Services\UserLevelService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 관리자가 등급 구간을 바꾼 뒤 전체 회원 등급을 다시 계산한다. 연달아 바꿔도 한 번만 대기열에 들어간다.
 */
class RecalculateUserLevels implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function handle(UserLevelService $levels): void
    {
        $levels->recalculateAll();
    }
}
