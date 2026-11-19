<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

/**
 * 지금은 Google·Apple 로그인만 사용하므로 Fortify의 라우트와 기능을 모두 끈다 (ADR-003).
 *
 * 이메일·비밀번호 가입을 추가할 때 (ADR-003 §8):
 *  1. 아래 ignoreRoutes()를 지우고 config/fortify.php의 features에 필요한 기능을 켠다.
 *  2. 스타터 키트 원본(커밋 9c6de37)의 FortifyServiceProvider, app/Actions/Fortify,
 *     resources/js/pages/auth 화면을 참고해 복원한다.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        //
    }
}
