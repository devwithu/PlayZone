<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\LoginProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SocialLoginRequest;
use App\Http\Responses\ApiResponse;
use App\Services\AppLoginService;
use Illuminate\Http\JsonResponse;

/**
 * 앱 소셜 로그인 (api-spec §7.1). 웹은 Auth\SocialLoginController(Socialite 리디렉션)를 쓴다.
 */
class SocialLoginController extends Controller
{
    /**
     * 소셜 로그인
     *
     * Google·Apple ID 토큰을 검증하고 Sanctum 토큰(60일)을 발급한다. 처음이면 가입 미완료(pending) 회원을 만든다.
     * `signup_completed`가 false면 앱은 가입 완료 화면(약관·닉네임)을 띄운다.
     *
     * 실패: `401 SOCIAL_TOKEN_INVALID`(토큰 검증 실패), `403 ACCOUNT_SUSPENDED`(정지 회원),
     * `403 REJOIN_RESTRICTED`(탈퇴 후 30일 이내). 요청 제한은 IP당 분당 10회.
     *
     * @unauthenticated
     */
    public function store(SocialLoginRequest $request, LoginProvider $provider, AppLoginService $service): JsonResponse
    {
        $result = $service->login(
            provider: $provider,
            idToken: $request->string('id_token')->value(),
            authorizationCode: $request->filled('authorization_code') ? $request->string('authorization_code')->value() : null,
            deviceName: $request->string('device_name')->value(),
        );

        $user = $result['user'];

        return ApiResponse::success([
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'signup_completed' => ! $user->isPending(),
            'user' => [
                'id' => $user->id,
                'nickname' => $user->nickname,
            ],
        ], '로그인되었습니다.');
    }
}
