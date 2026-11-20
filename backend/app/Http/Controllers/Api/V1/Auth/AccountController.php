<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteSignupRequest;
use App\Http\Resources\Api\V1\MeResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\SignupService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 앱 인증·계정 (api-spec §7.1). 가입 미완료(pending) 회원도 호출할 수 있다.
 */
class AccountController extends Controller
{
    /**
     * 내 정보
     *
     * 가입 완료 여부, 상태, 포인트 잔액, 연결된 소셜 계정.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new MeResource($this->user($request)->load('socialAccounts')));
    }

    /**
     * 가입 완료
     *
     * 만 14세 이상 확인, 이용약관·개인정보 처리방침 동의(필수), 마케팅 수신 동의(선택), 닉네임 설정.
     * 가입 포인트를 지급하고 `data.point`로 알려 준다. 이미 가입을 마친 회원이 다시 보내면 아무것도 바꾸지 않고
     * 현재 정보를 돌려준다(`point.earned` 0).
     * 요청 제한은 회원당 분당 5회.
     */
    public function completeSignup(CompleteSignupRequest $request, SignupService $service): JsonResponse
    {
        $result = $service->complete(
            user: $this->user($request),
            nickname: $request->string('nickname')->value(),
            marketingAgreed: $request->boolean('marketing'),
        );

        return ApiResponse::success([
            ...(new MeResource($result->user->load('socialAccounts')))->resolve($request),
            // 가입 포인트 (api-spec §3 data.point). 재시도면 earned 0
            'point' => $result->point->toArray(),
        ], '가입이 완료되었습니다.');
    }

    /**
     * 닉네임 확인
     *
     * 사용할 수 있으면 `available: true`. 형식 오류·중복도 422가 아니라 `available: false`와 이유(`message`)로 알려 준다.
     */
    public function checkNickname(Request $request, SignupService $service): JsonResponse
    {
        /** @query 확인할 닉네임 (2~12자, 한글·영문·숫자·밑줄) */
        $nickname = $request->query('nickname');

        return ApiResponse::success($service->checkNickname(is_string($nickname) ? $nickname : null, $this->user($request)));
    }

    /**
     * 로그아웃
     *
     * 현재 기기의 토큰만 폐기한다. 다른 기기의 로그인은 유지된다.
     */
    public function logout(Request $request): JsonResponse
    {
        // Sanctum 세션 인증을 꺼 두었으므로(config/sanctum.php) 현재 토큰은 항상 DB에 저장된 토큰이다
        $this->user($request)->currentAccessToken()->delete();

        return ApiResponse::success(message: '로그아웃되었습니다.');
    }

    /**
     * 회원 탈퇴
     *
     * 앱의 확인 화면(포인트 소멸, 30일 재가입 제한, 작성한 글은 "탈퇴한 회원"으로 남음)을 거친 뒤 호출한다.
     * 개인정보를 지우고 모든 기기의 로그인을 끊는다. Apple 로그인 계정은 Apple 연결도 폐기한다.
     * 같은 소셜 계정으로는 30일 동안 다시 가입할 수 없다.
     */
    public function destroy(Request $request, WithdrawalService $service): JsonResponse
    {
        $service->withdraw($this->user($request));

        return ApiResponse::success(message: '탈퇴되었습니다.');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
