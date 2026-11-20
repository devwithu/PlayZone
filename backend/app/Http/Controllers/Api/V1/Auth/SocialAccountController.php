<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\LoginProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LinkSocialAccountRequest;
use App\Http\Resources\Api\V1\MeResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\SocialAccountLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 소셜 계정 연결·해제 (api-spec §7.1, ADR-003 §7-1). 가입을 마친 회원만.
 */
class SocialAccountController extends Controller
{
    /**
     * 소셜 계정 연결
     *
     * 로그인한 회원에게 다른 Google·Apple 계정을 연결한다. 응답은 `GET /auth/me`와 같다.
     *
     * 실패: `401 SOCIAL_TOKEN_INVALID`, `409 SOCIAL_ACCOUNT_IN_USE`(다른 회원이 쓰는 계정),
     * `409 SOCIAL_PROVIDER_ALREADY_LINKED`(같은 제공자의 다른 계정이 이미 연결됨). 요청 제한은 IP당 분당 10회.
     */
    public function store(LinkSocialAccountRequest $request, LoginProvider $provider, SocialAccountLinkService $service): JsonResponse
    {
        $user = $this->user($request);

        $service->linkWithIdToken(
            user: $user,
            provider: $provider,
            idToken: $request->string('id_token')->value(),
            authorizationCode: $request->filled('authorization_code') ? $request->string('authorization_code')->value() : null,
        );

        return ApiResponse::success(
            new MeResource($user->load('socialAccounts')),
            "{$provider->label()} 계정을 연결했습니다.",
        );
    }

    /**
     * 소셜 계정 연결 해제
     *
     * 마지막 로그인 수단은 해제할 수 없다(`409 LAST_LOGIN_METHOD`). 연결되지 않은 제공자면 `404 NOT_FOUND`.
     * 응답은 `GET /auth/me`와 같다.
     */
    public function destroy(Request $request, LoginProvider $provider, SocialAccountLinkService $service): JsonResponse
    {
        $user = $this->user($request);

        $service->unlink($user, $provider);

        return ApiResponse::success(
            new MeResource($user->load('socialAccounts')),
            "{$provider->label()} 계정 연결을 해제했습니다.",
        );
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
