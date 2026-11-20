<?php

namespace App\Http\Controllers\Settings;

use App\Enums\LoginProvider;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SocialAccountLinkService;
use App\Services\SocialAuth\WebSocialiteDrivers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * 웹 설정 > 로그인 연결 (W-18, REQ-008, ADR-003 §7-1).
 * 연결은 제공자 화면을 거쳐 Auth\SocialLoginController::callback으로 돌아온다.
 */
class SocialAccountController extends Controller
{
    /** 연결을 시작한 제공자. 콜백에서 로그인과 연결을 구분하는 데 쓴다 */
    public const LINK_SESSION_KEY = 'social_link';

    /** 설정 화면에 보여 줄 결과 메시지 (외부 사이트에서 돌아오는 전체 페이지 이동이라 세션으로 전달) */
    public const MESSAGE_SESSION_KEY = 'socialAccountMessage';

    public function redirect(Request $request, LoginProvider $provider, WebSocialiteDrivers $drivers): SymfonyRedirectResponse
    {
        $request->session()->put(self::LINK_SESSION_KEY, $provider->value);

        return $drivers->forLinking($provider)->redirect();
    }

    public function destroy(Request $request, LoginProvider $provider, SocialAccountLinkService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $service->unlink($user, $provider);
        } catch (ApiException $e) {
            return self::backWithMessage('error', $e->getMessage());
        }

        return self::backWithMessage('success', "{$provider->label()} 계정 연결을 해제했습니다.");
    }

    /**
     * @param  'success'|'error'  $type
     */
    public static function backWithMessage(string $type, string $message): RedirectResponse
    {
        return to_route('profile.edit')->with(self::MESSAGE_SESSION_KEY, ['type' => $type, 'message' => $message]);
    }
}
