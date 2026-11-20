<?php

namespace App\Providers;

use App\Support\OpenApi\ApiErrorResponseTransformer;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Apple\AppleExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Apple 로그인 드라이버 등록 (socialiteproviders/apple)
        Event::listen(SocialiteWasCalled::class, [AppleExtendSocialite::class, 'handle']);

        $this->configureRateLimiting();

        // OpenAPI 문서(/docs/api)의 오류 응답을 실제 형식에 맞춘다
        Scramble::configure()->withDocumentTransformers(ApiErrorResponseTransformer::class);
    }

    /**
     * 요청 제한 (api-spec §6). 엔드포인트별 한도는 해당 기능을 만들 때 여기에 더한다.
     */
    protected function configureRateLimiting(): void
    {
        // 그 밖의 API: 분당 120회, 회원 또는 IP 기준
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // 소셜 로그인: 분당 10회, IP 기준
        RateLimiter::for('social-login', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('social-login:'.$request->ip()));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
