<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginProvider;
use App\Exceptions\LoginDeniedException;
use App\Http\Controllers\Controller;
use App\Services\SocialLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * 웹 소셜 로그인 (ADR-003 §3). 앱은 ID 토큰 API를 따로 쓴다.
 */
class SocialLoginController extends Controller
{
    public function redirect(LoginProvider $provider): SymfonyRedirectResponse
    {
        return $this->driver($provider)->redirect();
    }

    public function callback(Request $request, LoginProvider $provider, SocialLoginService $service): RedirectResponse
    {
        try {
            /** @var SocialiteUser&User $socialUser */
            $socialUser = $this->driver($provider)->user();
        } catch (Throwable $e) {
            Log::warning('Social login callback failed', ['provider' => $provider->value, 'error' => $e->getMessage()]);

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

    private function appleWebClientId(): ?string
    {
        $clientId = config('services.apple.client_id');

        return is_string($clientId) && $clientId !== '' ? $clientId : null;
    }

    private function driver(LoginProvider $provider): Provider
    {
        $driver = Socialite::driver($provider->value);

        // Apple은 콜백을 다른 사이트에서 POST로 보내(form_post) 세션 쿠키가 전달되지 않으므로 state 검증 없이 처리한다.
        if ($provider === LoginProvider::Apple && $driver instanceof AbstractProvider) {
            $driver->stateless();
        }

        return $driver;
    }
}
