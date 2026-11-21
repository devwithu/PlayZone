<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 랭킹 집계 (랭킹 정책 §3): 매시 정각. 서버 cron에 `* * * * * php artisan schedule:run`이 있어야 돈다
Schedule::command('rankings:snapshot')->hourly()->withoutOverlapping()->onOneServer();
