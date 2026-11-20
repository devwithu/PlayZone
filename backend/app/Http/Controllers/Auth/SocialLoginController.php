<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginProvider;
use App\Exceptions\ApiException;
use App\Exceptions\LoginDeniedException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\SocialAccountController;
use App\Models\User;
use App\Services\SocialAccountLinkService;
use App\Services\SocialAuth\WebSocialiteDrivers;
use App\Services\SocialLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as OAuthUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * 웹 소셜 로그인 (ADR-003 §3). 앱은 ID 토큰 API를 따로 쓴다.
 * 제공자에 등록된 콜백 주소가 하나이므로, 설정 화면의 계정 연결(ADR-003 §7-1)도 이 콜백으로 돌아온다.
 */
class SocialLoginController extends Controller
{
    public function __construct(
        private readonly WebSocialiteDrivers $drivers,
        private readonly SocialAccountLinkService $linkService,
    ) {}

    public function redirect(Request $request, LoginProvider $provider): SymfonyRedirectResponse
    {
        // 콜백은 APP_URL 주소로 돌아온다. 다른 주소(예: 127.0.0.1 ↔ localhost, www 유무)에서 시작하면
        // 세션 쿠키가 콜백에 오지 않아 state 검증이 실패하므로, 먼저 APP_URL 주소로 옮겨서 시작한다.
        $appOrigin = rtrim((string) config('app.url'), '/');
        if ($appOrigin !== '' && $request->getSchemeAndHttpHost() !== $appOrigin) {
            return redirect()->away($appOrigin.$request->getRequestUri());
        }

        return $this->drivers->forLogin($provider)->redirect();
    }

    public function callback(Request $request, LoginProvider $provider, SocialLoginService $service): RedirectResponse
    {
        $linkingUser = $this->linkingUser($request, $provider);
        if ($linkingUser !== null) {
            return $this->link($linkingUser, $provider);
        }

        try {
            /** @var SocialiteUser&OAuthUser $socialUser */
            $socialUser = $this->drivers->forLogin($provider)->user();
        } catch (Throwable $e) {
            Log::warning('Social login callback failed', ['provider' => $provider->value, 'exception' => $e::class, 'error' => $e->getMessage()]);

            return to_route('login')->with('loginError', '로그인하지 못했습니다. 다시 시도해 주세요.');
        }

        try {
            $user = $service->findOrCreateUser(
                provider: $provider,
                providerUserId: (string) $socialUser->getId(),
                email: $socialUser->getEmail(),
                emailVerified: filter_var($socialUser->getRaw()['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
                // Apple 탈퇴 시 토큰 폐기에 필요 (ADR-003 §5)
                refreshToken: $provider === LoginProvider::Apple ? $socialUser->refreshToken : null,
                // 웹 로그인 토큰은 Services ID로 발급된다
                refreshTokenClientId: $provider === LoginProvider::Apple ? $this->appleWebClientId() : null,
            );
        } catch (LoginDeniedException $e) {
            return to_route('login')->with('loginError', $e->getMessage());
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->isPending()) {
            return to_route('signup.show');
        }

        return redirect()->intended(route('home'));
    }

    /**
     * 설정 화면에서 연결을 시작한 회원이면 그 회원을 돌려준다.
     */
    private function linkingUser(Request $request, LoginProvider $provider): ?User
    {
        $linking = $request->session()->pull(SocialAccountController::LINK_SESSION_KEY);
        $user = $request->user();

        if ($linking !== $provider->value || ! $user instanceof User || ! $user->isActive()) {
            return null;
        }

        return $user;
    }

    private function link(User $user, LoginProvider $provider): RedirectResponse
    {
        try {
            /** @var SocialiteUser&OAuthUser $socialUser */
            $socialUser = $this->drivers->forLinking($provider)->user();
        } catch (Throwable $e) {
            Log::warning('Social link callback failed', ['provider' => $provider->value, 'exception' => $e::class, 'error' => $e->getMessage()]);

            return SocialAccountController::backWithMessage('error', '계정을 연결하지 못했습니다. 다시 시도해 주세요.');
        }

        try {
            $this->linkService->link(
                user: $user,
                provider: $provider,
                providerUserId: (string) $socialUser->getId(),
                email: $socialUser->getEmail(),
                refreshToken: $provider === LoginProvider::Apple ? $socialUser->refreshToken : null,
                refreshTokenClientId: $provider === LoginProvider::Apple ? $this->appleWebClientId() : null,
            );
        } catch (ApiException $e) {
            return SocialAccountController::backWithMessage('error', $e->getMessage());
        }

        return SocialAccountController::backWithMessage('success', "{$provider->label()} 계정을 연결했습니다.");
    }

    private function appleWebClientId(): ?string
    {
        $clientId = config('services.apple.client_id');

        return is_string($clientId) && $clientId !== '' ? $clientId : null;
    }
}
