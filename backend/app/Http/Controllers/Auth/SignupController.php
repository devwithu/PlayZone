<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteSignupRequest;
use App\Services\SignupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 가입 완료: 만 14세 확인, 약관 동의, 닉네임 설정 (W-03, REQ-003~005).
 */
class SignupController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('auth/CompleteSignup');
    }

    public function store(CompleteSignupRequest $request, SignupService $service): RedirectResponse
    {
        $result = $service->complete(
            user: $request->user(),
            nickname: $request->string('nickname')->toString(),
            marketingAgreed: $request->boolean('marketing'),
        );

        if ($result->point->earned > 0) {
            Inertia::flash('toast', ['type' => 'success', 'message' => "가입을 환영합니다! {$result->point->earned}P를 받았습니다."]);
        }

        return redirect()->intended(route('home'));
    }

    /**
     * 닉네임 사용 가능 여부 (입력 중 확인용).
     */
    public function checkNickname(Request $request, SignupService $service): JsonResponse
    {
        return response()->json($service->checkNickname($request->string('nickname')->value() ?: null, $request->user()));
    }
}
