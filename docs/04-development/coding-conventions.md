# 코딩 규칙

> 작성일: 2026-11-20. 기능을 만들면서 정한 규칙을 이곳에 더한다.

## 1. API 응답 (`/api/v1`)

설계 기준은 [API 명세](../03-architecture/api/api-spec.md) §3~§5다.

| 상황 | 쓰는 것 |
|---|---|
| 성공 | `ApiResponse::success($data, '메시지')` |
| 생성 (201) | `ApiResponse::created($data, '메시지')` |
| 페이지 목록 | `ApiResponse::paginated($query->paginate($request->perPage()), GameResource::class)` |
| 업무 규칙 위반 | `throw ApiException::of(ApiErrorCode::AttendanceAlreadyDone)` |
| 입력값 오류 | FormRequest 검증 실패 → 자동으로 `422 VALIDATION_FAILED` |

- 컨트롤러는 반환 타입을 `JsonResponse`로 적는다. Scramble이 `ApiResponse` 호출을 분석해 OpenAPI 문서를 만든다.
- `data`에는 API Resource(`app/Http/Resources`)나 배열을 넣는다. 모델을 그대로 넣지 않는다(숨길 필드가 새기 쉽다).
- 목록 요청은 `App\Http\Requests\Api\V1\PaginatedRequest`를 상속한다(`page`, `per_page` 기본 20·최대 50 검증). 규칙을 더할 때는 `[...parent::rules(), ...]`로 합친다.
- `data`가 없으면 `{}`로, `errors`가 없으면 `{}`로 응답한다(배열 `[]`이 아님).

### 오류 처리

- `/api/*`에서 나는 예외는 모두 `App\Exceptions\ApiExceptionRenderer`가 `{ success: false, code, message, errors }`로 바꾼다. 컨트롤러에서 try/catch로 오류 응답을 직접 만들지 않는다.
- 서비스(`app/Services`)는 업무 규칙 위반을 `ApiException`(또는 이를 상속한 예외, 예: `LoginDeniedException`)으로 알린다. 웹 컨트롤러는 필요하면 이 예외를 잡아 화면 메시지로 바꾼다.
- 프레임워크 예외(404, 403, 429 등)는 영어 문구 대신 `ApiErrorCode::defaultMessage()`의 한국어 문구로 응답한다. 특정 문구가 필요하면 `ApiException`을 던진다.
- 500 응답에는 상세 내용을 넣지 않는다. 원인은 로그(`composer dev`의 pail)에서 본다.
- 새 오류 코드는 `App\Enums\ApiErrorCode`에 추가하고 API 명세 §5 표도 고친다. 앱이 값으로 분기하므로 **기존 값은 바꾸지 않는다.**

### 인증·요청 제한

- 앱 인증은 Sanctum Bearer 토큰(60일, `config/sanctum.php`)이다. 홈페이지는 API를 쓰지 않으므로 Sanctum의 세션(stateful) 인증은 꺼 두었다.
- 기본 요청 제한은 분당 120회(회원 또는 IP)다. 엔드포인트별 한도(api-spec §6)는 `AppServiceProvider::configureRateLimiting()`에 리미터를 더하고 라우트에 `throttle:이름`을 붙인다.
