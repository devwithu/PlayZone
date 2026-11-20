<?php

namespace App\Services\SocialAuth;

use App\Enums\LoginProvider;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Apple\Provider as AppleProvider;

/**
 * 웹(Socialite 리디렉션) 드라이버 설정. 리디렉션과 콜백은 반드시 같은 설정을 써야 한다.
 *
 * Apple은 이메일·이름을 요청하면 콜백을 다른 사이트에서 POST(form_post)로 보내고,
 * 이때 SameSite=Lax 세션 쿠키가 오지 않는다.
 * - 로그인: 세션 없이(stateless) 처리하고, CSRF 방지용 nonce는 암호화 쿠키(SameSite=None)로 들고 다닌다.
 * - 계정 연결: 누가 연결하는지 세션으로 알아야 하므로, scope 없이 요청해 GET 콜백(response_mode=query)을 받는다.
 */
class WebSocialiteDrivers
{
    /** Apple 드라이버가 nonce를 담는 쿠키. 드라이버가 직접 암호화하므로 Laravel 쿠키 암호화에서 뺀다 */
    public const APPLE_NONCE_COOKIE = 'socialite_apple_nonce';

    public function forLogin(LoginProvider $provider): Provider
    {
        $driver = Socialite::driver($provider->value);

        if ($driver instanceof AppleProvider) {
            $driver->stateless()->cookieNonce();
        }

        return $driver;
    }

    public function forLinking(LoginProvider $provider): Provider
    {
        $driver = Socialite::driver($provider->value);

        if ($driver instanceof AppleProvider) {
            // scopes()는 기본 scope(name email)에 더하므로 setScopes()로 비운다. scope가 있으면 Apple이 query를 거부한다
            $driver->setScopes([])->with(['response_mode' => 'query']);
        }

        return $driver;
    }
}
