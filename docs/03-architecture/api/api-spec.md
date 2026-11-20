# API 명세 (초안)

> 상태: **확정** (2026-11-19, §10)
> 작성일: 2026-11-19
> 근거 문서: [기술 개발 설계서](../../00-overview/game-evaluation-development-design.md) §5, [ERD](../database/erd.md), [포인트 정책](../../01-planning/policies/point-policy.md), [리뷰 정책](../../01-planning/policies/review-policy.md)

이 문서는 API의 **설계 기준**이다. 요청·응답의 전체 스키마는 1단계에서 코드로부터 OpenAPI 문서를 자동 생성해 관리한다(§9). 설계서와 달라진 부분은 §8에 모았다.

## 1. 기본 규칙

| 항목 | 규칙 |
|---|---|
| 기본 주소 | `https://{도메인}/api/v1` (웹과 같은 도메인, §10-1) |
| 형식 | 요청·응답 모두 JSON. 헤더 `Accept: application/json` 필수 |
| 버전 | URL에 버전(`/v1`). 하위 호환이 깨지는 변경은 `/v2`로 |
| 이름 | URL은 kebab-case 복수형, JSON 필드는 snake_case |
| ID | 리소스는 숫자 `id`로 접근. 게시판만 `slug` 사용 |
| 시각 | ISO 8601 UTC (`2026-11-19T03:00:00Z`). 화면 표시는 클라이언트가 KST로 변환 |
| 날짜 | 출석일 등 서비스 일자는 `YYYY-MM-DD` (KST 기준) |
| 점수 | 정수 1~10. 평균은 소수 첫째 자리 숫자(`8.5`), 리뷰 3개 미만이면 `null` |
| 파일 URL | 응답에는 전체 URL을 준다 (서버가 `Storage::url()`로 생성) |

## 2. 인증

로그인은 **Google·Apple 소셜 로그인만** 허용한다. 이메일·비밀번호 가입은 없다 ([ADR-003](../../07-meetings/decisions/ADR-003-social-login.md)).

| 클라이언트 | 방식 |
|---|---|
| Unity 앱 | 네이티브 SDK로 받은 ID 토큰을 `POST /auth/social/{provider}`로 보내고, Sanctum 토큰을 받는다. 이후 `Authorization: Bearer {token}` |
| 홈페이지 | **`/api/v1`을 쓰지 않는다.** Inertia 웹 라우트(`routes/web.php`)와 세션 로그인(Socialite 리디렉션)을 쓴다 ([ADR-002](../../07-meetings/decisions/ADR-002-tech-stack.md)). 웹 컨트롤러와 API 컨트롤러는 같은 서비스와 검증 규칙을 공유한다 |
| 관리자 | Filament 화면만 사용. 관리자 전용 API는 1차에 두지 않음 |

- 소셜 로그인 요청에 `device_name`(예: `Unity Android`)을 보낸다. 같은 `device_name`으로 다시 로그인하면 이전 토큰을 폐기한다.
- 서버는 ID 토큰의 서명·발급자·대상·만료를 검증하고, 앱이 따로 보낸 이메일·이름은 믿지 않는다.
- 토큰 유효기간은 **60일**. 만료되면 `401 UNAUTHENTICATED`를 받고 다시 로그인한다 (§10-2).
- 로그아웃은 현재 기기의 토큰만 폐기한다. 탈퇴하면 모든 토큰을 폐기한다.
- **가입 미완료(`pending`) 회원**은 `/auth/*` 엔드포인트만 호출할 수 있다. 그 밖의 로그인 필요 API는 `403 SIGNUP_INCOMPLETE`를 받는다.
- 앱은 토큰을 Android Keystore / iOS Keychain에 저장하고 로그에 남기지 않는다(설계서 §5.10).

## 3. 응답 형식

**성공**
```json
{
  "success": true,
  "message": "게임 목록을 불러왔습니다.",
  "data": { }
}
```

**목록 (페이지)**
```json
{
  "success": true,
  "message": "",
  "data": {
    "items": [],
    "pagination": { "current_page": 1, "last_page": 10, "per_page": 20, "total": 200 }
  }
}
```
- 쿼리: `page` (기본 1), `per_page` (기본 20, 최대 50)

**실패**
```json
{
  "success": false,
  "code": "ATTENDANCE_ALREADY_DONE",
  "message": "이미 오늘 출석을 완료했습니다.",
  "errors": {}
}
```
- `code`: 앱이 분기 처리에 쓰는 고정 문자열 (§5). `message`는 화면 표시용이라 바뀔 수 있다.
- `errors`: 입력값 오류일 때 필드별 메시지 `{ "email": ["이미 사용 중인 이메일입니다."] }`

**포인트가 지급되는 요청**은 `data.point`를 함께 준다. 앱은 이 값으로 바로 화면을 갱신한다.
```json
"point": { "earned": 10, "balance": 1250, "type": "attendance", "limited": false }
```
- 일일 한도에 걸리면 `earned: 0`, `limited: true`. 요청 자체는 성공이다(포인트 정책 §3).

## 4. HTTP 상태 코드

| 코드 | 사용 |
|---|---|
| 200 | 조회·수정·삭제 성공 (삭제도 응답 본문을 준다) |
| 201 | 생성 성공 |
| 400 | 그 밖의 잘못된 요청 (형식 오류 등) |
| 401 | 로그인 필요, 토큰 만료 |
| 403 | 권한 없음, 제재 중, 이메일 미인증 |
| 404 | 없음 (비공개·삭제된 리소스 포함) |
| 405 | 지원하지 않는 HTTP 메서드 |
| 409 | 중복·상태 충돌 (이미 출석, 이미 리뷰 작성 등) |
| 422 | 입력값 오류 |
| 429 | 요청 횟수 초과 |
| 500 | 서버 오류 (상세 내용은 응답하지 않음) |

## 5. 오류 코드

| code | HTTP | 상황 |
|---|---|---|
| `VALIDATION_FAILED` | 422 | 입력값 오류 |
| `UNAUTHENTICATED` | 401 | 로그인 필요, 토큰 만료 |
| `SOCIAL_TOKEN_INVALID` | 401 | 소셜 ID 토큰 검증 실패 (서명·대상·만료) |
| `SIGNUP_INCOMPLETE` | 403 | 가입 미완료 (약관 동의·닉네임 설정 필요) |
| `ACCOUNT_SUSPENDED` | 403 | 정지된 회원 |
| `REJOIN_RESTRICTED` | 403 | 탈퇴 후 30일 이내 같은 소셜 계정으로 재가입 |
| `SOCIAL_ACCOUNT_IN_USE` | 409 | 연결하려는 소셜 계정이 이미 다른 회원에 연결됨 |
| `SOCIAL_PROVIDER_ALREADY_LINKED` | 409 | 같은 제공자의 다른 계정이 이미 연결됨 (회원당 제공자별 1개). 먼저 해제해야 한다 |
| `LAST_LOGIN_METHOD` | 409 | 남은 로그인 수단이 하나뿐이라 연결 해제 불가 |
| `FORBIDDEN` | 403 | 권한 없음 (남의 글 수정 등) |
| `REVIEW_BANNED` | 403 | 리뷰 작성 제한 중. `errors.ends_at` 포함 (영구면 `null`) |
| `NOT_FOUND` | 404 | 리소스 없음 (없는 주소 포함) |
| `METHOD_NOT_ALLOWED` | 405 | 지원하지 않는 HTTP 메서드. `Allow` 헤더 포함 |
| `ATTENDANCE_ALREADY_DONE` | 409 | 오늘 이미 출석 |
| `REVIEW_ALREADY_EXISTS` | 409 | 이 게임에 이미 리뷰가 있음. `errors.review_id` 포함 |
| `REVIEW_HIDDEN_EXISTS` | 409 | 관리자가 숨긴 리뷰가 있어 새로 쓸 수 없음 |
| `GAME_NOT_RELEASED` | 409 | 출시 전 게임 |
| `ALREADY_REPORTED` | 409 | 이미 신고함 |
| `CANNOT_TARGET_OWN` | 409 | 본인 리뷰·글 추천 또는 신고 |
| `NOT_EDITABLE` | 409 | 수정할 수 없는 상태 (숨김·블라인드) |
| `INSUFFICIENT_POINTS` | 409 | 포인트 부족 (이벤트 응모) |
| `EVENT_CLOSED` | 409 | 이벤트 기간 아님 또는 정원 마감 |
| `BAD_REQUEST` | 400 등 | 위 코드에 해당하지 않는 그 밖의 4xx. HTTP 상태는 실제 값(400, 413 등)을 따른다 |
| `RATE_LIMITED` | 429 | 요청 횟수 초과. `Retry-After` 헤더(초) 포함 |
| `SERVER_ERROR` | 500 등 | 서버 오류 (5xx). 상세 내용은 응답하지 않는다 |

코드 목록의 원본은 `backend/app/Enums/ApiErrorCode.php`다. 코드를 추가하면 이 표도 함께 고친다.

## 6. 요청 제한 (Rate Limit)

| 대상 | 제한 | 기준 |
|---|---|---|
| 소셜 로그인 | 분당 10회 | IP |
| 가입 완료 (닉네임 설정) | 분당 5회 | 회원 |
| 리뷰·게시글 작성 | 분당 3회 | 회원 |
| 댓글 작성, 추천, 신고 | 분당 20회 | 회원 |
| 그 밖의 API | 분당 120회 | 회원 또는 IP |

## 7. 엔드포인트

인증 열: `-` 불필요, `O` 로그인 + 가입 완료 필요, `P` 로그인만 필요(가입 미완료도 가능). 굵은 글씨는 설계서에 없던 엔드포인트다.

### 7.1 인증·계정

설계서의 이메일 가입·로그인·비밀번호 재설정 엔드포인트는 소셜 로그인 전환으로 **없앴다** (ADR-003).

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| **POST** | **/auth/social/{provider}** | 소셜 로그인 (`google`/`apple`). 처음이면 계정 생성. Sanctum 토큰 발급 | - |
| **POST** | **/auth/signup/complete** | 가입 완료 (약관 동의, 만 14세 이상 확인, 닉네임). 가입 포인트 지급 | P |
| **GET** | **/auth/nickname-check** | 닉네임 사용 가능 여부 (`nickname`) | P |
| POST | /auth/logout | 로그아웃 (현재 토큰 폐기) | P |
| GET | /auth/me | 내 정보 (가입 완료 여부, 잔액·등급·제재 상태, 연결된 소셜 계정) | P |
| **DELETE** | **/auth/me** | 회원 탈퇴. 모든 토큰 폐기, Apple·Google 토큰 폐기 (ADR-003 §5) | P |
| **POST** | **/auth/social-accounts/{provider}** | 다른 소셜 계정 연결 (ID 토큰 전송) | O |
| **DELETE** | **/auth/social-accounts/{provider}** | 소셜 계정 연결 해제 (마지막 하나는 불가) | O |
| **POST** | **/devices** | FCM 기기 토큰 등록·갱신 | O |
| **DELETE** | **/devices/{token}** | 기기 토큰 삭제 (로그아웃 시) | P |

**소셜 로그인 요청 예시**
```json
{
  "id_token": "eyJ...",
  "authorization_code": "c1a...",
  "device_name": "Unity iOS"
}
```
- `authorization_code`는 Apple만 보낸다. 서버가 이 코드로 refresh token을 받아 탈퇴 시 토큰 폐기에 쓴다. 교환에 실패해도 로그인은 성공한다(서버 로그에 경고).
- 서버가 허용하는 ID 토큰 대상(`aud`)은 Google은 `GOOGLE_ID_TOKEN_AUDIENCES`(Unity가 ID 토큰을 요청할 때 쓰는 클라이언트 ID, 보통 웹 클라이언트 ID), Apple은 `APPLE_ID_TOKEN_AUDIENCES`(iOS 앱 번들 ID)다. Apple 코드 교환에는 `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY`(.p8)가 필요하다.
- 실패: `401 SOCIAL_TOKEN_INVALID`, `403 ACCOUNT_SUSPENDED`, `403 REJOIN_RESTRICTED`, `422 VALIDATION_FAILED`, `429 RATE_LIMITED`

**소셜 로그인 응답 예시**
```json
{
  "success": true,
  "message": "로그인되었습니다.",
  "data": {
    "access_token": "발급된_토큰",
    "token_type": "Bearer",
    "signup_completed": false,
    "user": { "id": 100, "nickname": null }
  }
}
```
- `signup_completed: false`면 앱이 가입 완료 화면(약관·닉네임)을 띄운다.

**가입 완료 요청 예시** (`POST /auth/signup/complete`, 웹 가입 완료와 같은 검증 규칙)
```json
{
  "nickname": "젤다러버",
  "age_confirmed": true,
  "terms": true,
  "privacy": true,
  "marketing": false
}
```
- `age_confirmed`, `terms`, `privacy`는 `true`여야 한다. 닉네임은 2~12자 한글·영문·숫자·밑줄, 대소문자 구분 없이 중복 불가.
- 응답 `data`는 `GET /auth/me`와 같다. 이미 가입을 마친 회원이 다시 보내면 아무것도 바꾸지 않고 현재 정보를 준다(재시도에 안전).
- 가입 포인트(`data.point`)는 3단계에서 `PointService`를 만들 때 추가한다.

**내 정보 응답 예시** (`GET /auth/me`)
```json
{
  "success": true,
  "message": "",
  "data": {
    "id": 100,
    "nickname": "젤다러버",
    "status": "active",
    "signup_completed": true,
    "point_balance": 1250,
    "lifetime_points": 3000,
    "marketing_agreed": false,
    "signup_completed_at": "2026-11-20T03:00:00Z",
    "social_accounts": [{ "provider": "google", "connected_at": "2026-11-20T02:58:00Z" }]
  }
}
```
- `status`: `pending`(가입 미완료), `active`, `suspended`. 이메일은 주지 않는다.
- 등급과 제재 상태는 3단계에서 `user_levels`·`user_sanctions` 테이블을 만들 때 추가한다.

**소셜 계정 연결** (`POST /auth/social-accounts/{provider}`): 요청은 `{ "id_token": "eyJ...", "authorization_code": "c1a..." }`(코드는 Apple만), 응답 `data`는 `GET /auth/me`와 같다. 해제(`DELETE`)도 같은 형식으로 응답한다. 이미 연결된 같은 계정을 다시 보내면 성공으로 처리한다. Apple 계정을 해제하면 Apple 토큰도 폐기한다.

**닉네임 확인** (`GET /auth/nickname-check?nickname=젤다러버`): `data: { "available": true, "message": "사용할 수 있는 닉네임입니다." }`. 형식 오류·중복도 422가 아니라 `available: false`와 이유로 알려 준다. 본인의 현재 닉네임은 사용 가능으로 나온다.

### 7.2 게임

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| GET | /games | 목록·검색·필터 | - |
| GET | /games/{id} | 상세 (평균, 세부 평균, 출시일, 내 리뷰 ID·관심 여부) | - |
| GET | /games/{id}/rating-summary | 점수 분포(1~10별 리뷰 수), 세부 항목 평균 | - |
| GET | /genres | 장르 목록 | - |
| GET | /platforms | 플랫폼 목록 | - |
| **GET** | **/rating-categories** | 세부 평가 항목 목록 (활성만) | - |

`GET /games` 쿼리

| 파라미터 | 설명 |
|---|---|
| `q` | 검색어 (제목, 2자 이상, `pg_trgm`) |
| `genre`, `platform` | slug, 쉼표로 여러 개 |
| `release_status` | `released` / `upcoming` |
| `sort` | `popular`(리뷰 수, 기본), `rating`, `latest_release`, `title` |

로그인 상태면 상세 응답에 `my_review_id`, `is_favorite`, `release_alert`를 채운다.

**구현 메모 (2026-11-21)**

- 공개(`published`) 게임만 보인다. 작성 중·숨김 게임과 없는 ID는 `404 NOT_FOUND`.
- `q`는 제목 중간 일치(`ILIKE`)다. `%`, `_`는 글자 그대로 찾는다. 1자는 `422 VALIDATION_FAILED`.
- `genre`, `platform`은 쉼표로 여러 개를 주면 **하나라도 해당하는** 게임을 찾는다. 장르와 플랫폼을 함께 주면 둘 다 만족해야 한다. 없는 slug는 결과가 비어 있을 뿐 오류가 아니다.
- `release_status=released`: 한 플랫폼이라도 출시됨(= 리뷰 가능). `upcoming`: 어느 플랫폼에도 출시되지 않았고 취소되지 않은 출시 계획(미정 포함)이 있음.
- `sort=rating`은 평균이 없는(리뷰 3개 미만) 게임을, `latest_release`는 출시되지 않은 게임을 뒤로 보낸다. 같은 값이면 최신 등록 순(`title`은 ID 순)이다.
- 목록 항목: `id`, `title`, `slug`(웹 주소), `cover_url`, `genres`·`platforms`(`{id, name, slug}`), `review_count`, `rating_avg`, `is_reviewable`, `primary_release`(`{release_date, release_status, display_date}`: 출시됐으면 가장 이른 출시일, 아니면 가장 가까운 예정일, 계획이 없으면 `null`).
- 상세: 목록 항목에 더해 `description`, `developer`, `publisher`, `screenshot_urls`, `banner_url`, `favorite_count`, `release_dates`(`{platform, release_date, date_precision, release_status, display_date}`, 출시일 순, 미정은 뒤로).
- 출시일 `release_date`는 KST 날짜 `YYYY-MM-DD`다. 월·분기·연 단위는 그 기간의 첫날이므로, 앱은 `display_date`(예: `2027년 2분기`, `미정`)를 그대로 보여 준다.
- `/genres`, `/platforms`는 페이지 없이 관리자가 정한 순서의 전체 목록(`data` 배열)을 준다.
- 상세의 `is_favorite`, `release_alert`: 인증이 필요 없는 API지만 Bearer 토큰을 보내면 그 회원 기준으로 채운다. 토큰이 없으면 `null`이다. 이때 `favorite_count`도 최신 값이다.
- 아직 없는 것: 세부 항목 평균, `my_review_id`, `/games/{id}/rating-summary`, `/rating-categories`는 리뷰(3단계)에서 추가한다.

### 7.3 리뷰

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| GET | /games/{id}/reviews | 리뷰 목록 | - |
| POST | /games/{id}/reviews | 리뷰 작성 (포인트 지급) | O |
| GET | /reviews/{id} | 리뷰 상세 | - |
| PUT | /reviews/{id} | 리뷰 수정 (수정 이력 저장) | O |
| DELETE | /reviews/{id} | 리뷰 삭제 (`deleted`) | O |
| POST | /reviews/{id}/like | 추천 (작성자 포인트 지급) | O |
| DELETE | /reviews/{id}/like | 추천 취소 | O |
| **GET** | **/reviews/{id}/comments** | 댓글 목록 | - |
| POST | /reviews/{id}/comments | 댓글 작성 (포인트 지급) | O |
| **PUT** | **/review-comments/{id}** | 댓글 수정 | O |
| **DELETE** | **/review-comments/{id}** | 댓글 삭제 | O |
| POST | /reviews/{id}/reports | 신고 | O |

- 목록 쿼리 `sort`: `likes`(기본), `latest`, `score_high`, `score_low`
- 공개(`published`)가 아닌 리뷰는 작성자 본인에게만 보이고 그 밖에는 404다. 본인에게는 `status`, `hidden_reason`을 준다.

**리뷰 작성 요청 예시**
```json
{
  "title": "오픈월드의 교과서",
  "content": "50자 이상의 본문 ...",
  "platform_id": 3,
  "overall_score": 9,
  "ratings": { "graphics": 9, "sound": 8, "story": 10 },
  "has_spoiler": false
}
```
- `ratings`는 선택이고, 넣은 항목만 저장한다(키는 `rating-categories`의 slug).

**리뷰 작성 실패 순서** (먼저 걸리는 것을 응답)
`REVIEW_BANNED` → `GAME_NOT_RELEASED` → `REVIEW_HIDDEN_EXISTS` → `REVIEW_ALREADY_EXISTS` → `VALIDATION_FAILED`

### 7.4 출시 일정

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| GET | /releases | 기간별 출시 일정 (`from`, `to`, `platform`) | - |
| GET | /releases/upcoming | 출시 예정 (오늘 이후, 가까운 순) | - |
| GET | /games/{id}/releases | 게임별 플랫폼 출시일 | - |
| POST | /games/{id}/release-alerts | 출시 알림 켜기 | O |
| DELETE | /games/{id}/release-alerts | 출시 알림 끄기 | O |

출시 알림은 `favorite_games.release_alert` 컬럼이다(ERD §9). 알림을 켜면 관심 게임에 없을 때 함께 등록한다. 알림을 꺼도 관심 게임은 유지한다.

출시일 응답은 `release_date`와 `date_precision`을 함께 준다. 앱은 `date_precision`에 따라 "2027년 3월", "2027년 1분기", "미정"처럼 표시한다.

**구현 메모 (2026-11-21)**

- 날짜는 모두 KST 기준이다. 공개 게임만 보이고, 취소된 출시일은 일정에서 빠진다.
- 일정 항목: `game`(`{id, title, slug, cover_url}`), `platforms`, `release_date`, `date_precision`, `release_status`, `display_date`. 같은 게임이 같은 날(같은 정밀도·상태)에 여러 플랫폼으로 나오면 한 항목으로 묶는다.
- 월·분기·연 단위 출시일은 그 기간 전체로 본다. 예: 2027년 1분기 = 2027-01-01~03-31.
- `GET /releases`: 출시일 기간이 `from`~`to`와 겹치는 항목(출시 완료 포함, 미정 제외)을 페이지 없이 준다. `from`, `to`는 함께 주고 양 끝 포함 93일 이내다. 빼면 이번 달이다. 1분기 게임은 1·2·3월 조회에 모두 나오므로 앱은 `date_precision`으로 "N월 중", "분기·연 단위" 묶음을 나눠 보여 준다(화면 설계 W-08).
- `GET /releases/upcoming`: 출시되지 않았고 기간이 오늘 이후로 남은 항목을 페이지 단위로 준다. 미정은 맨 뒤다.
- 정렬은 기간이 끝나는 날 순이다. 그래서 "12월 중"은 12월의 날짜별 출시 뒤에 온다. 끝나는 날이 같으면 일 → 월 → 분기 → 연 단위, 그다음 제목 순이다.
- `platform`은 slug를 쉼표로 여러 개 줄 수 있다. 항목의 `platforms`에는 고른 플랫폼만 들어간다.
- `GET /games/{id}/releases`: 게임 상세의 `release_dates`와 같다. 취소된 플랫폼도 상태와 함께 준다.
- 출시 알림 켜기·끄기는 §7.7 구현 메모를 따른다.

### 7.5 커뮤니티

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| GET | /boards | 게시판 목록 | - |
| GET | /boards/{slug}/posts | 게시글 목록 (`q`, `sort`: `latest`/`likes`) | - |
| POST | /boards/{slug}/posts | 게시글 작성 (포인트 지급) | O |
| GET | /posts/{id} | 게시글 상세 (조회수 증가) | - |
| PUT | /posts/{id} | 게시글 수정 | O |
| DELETE | /posts/{id} | 게시글 삭제 | O |
| **POST** | **/posts/{id}/attachments** | 첨부파일 업로드 (multipart) | O |
| **GET** | **/posts/{id}/comments** | 댓글 목록 | - |
| POST | /posts/{id}/comments | 댓글 작성 (포인트 지급) | O |
| **PUT** | **/post-comments/{id}** | 댓글 수정 | O |
| **DELETE** | **/post-comments/{id}** | 댓글 삭제 | O |
| POST | /posts/{id}/like | 추천 | O |
| **DELETE** | **/posts/{id}/like** | 추천 취소 | O |
| **POST** | **/posts/{id}/reports** | 신고 | O |

첨부파일 제한(형식·용량)은 요구사항 정의에서 정한다.

### 7.6 출석·포인트·랭킹

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| GET | /attendance/today | 오늘 출석 여부, 현재 연속 일수 | O |
| POST | /attendance | 출석 (포인트 지급) | O |
| GET | /attendance/history | 월별 출석 기록 (`month=2026-11`) | O |
| GET | /points/balance | 잔액, 누적 획득 포인트, 등급 | O |
| GET | /points/history | 포인트 거래 내역 (`type` 필터) | O |
| GET | /rankings | 랭킹 (`type`, `date`) | - |

**출석 응답 예시**
```json
{
  "success": true,
  "message": "출석했습니다. 7일 연속 출석 보너스를 받았습니다!",
  "data": {
    "attendance_date": "2026-11-19",
    "streak_days": 7,
    "point": { "earned": 60, "balance": 1250, "type": "attendance", "limited": false },
    "bonuses": [{ "type": "attendance_streak_7", "earned": 50 }]
  }
}
```
- 요청 본문은 없다. 날짜와 포인트는 서버가 정한다(설계서 §7.1).

### 7.7 마이페이지·알림

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| GET | /mypage | 대시보드 (잔액, 등급, 출석, 작성 수) | O |
| PUT | /mypage/profile | 닉네임·소개 수정 | O |
| **POST** | **/mypage/profile/image** | 프로필 이미지 업로드 (multipart) | O |
| GET | /mypage/reviews | 내 리뷰 (숨김·블라인드 포함, 상태 표시) | O |
| GET | /mypage/posts | 내 게시글 | O |
| GET | /mypage/favorites | 관심 게임 | O |
| POST | /games/{id}/favorite | 관심 게임 등록 | O |
| DELETE | /games/{id}/favorite | 관심 게임 해제 (출시 알림도 함께 해제) | O |
| GET | /notifications | 알림 목록 (`unread_only`) | O |
| PUT | /notifications/{id}/read | 읽음 처리 | O |
| **PUT** | **/notifications/read-all** | 모두 읽음 | O |

**관심 게임·출시 알림 구현 메모 (2026-11-21)**

- `POST/DELETE /games/{id}/favorite`, `POST/DELETE /games/{id}/release-alerts`는 모두 멱등이다. 이미 그 상태여도 성공하고, 결과 상태 `{is_favorite, release_alert, favorite_count}`를 `data`로 준다.
- 새로 관심 게임에 등록하면 출시 알림이 켜진다(ERD 기본값). 해제하면 알림도 함께 사라진다. 알림 끄기는 관심 게임이 아니면 아무것도 하지 않는다.
- 등록·알림 켜기는 공개 게임만 된다(아니면 `404 NOT_FOUND`). 해제·알림 끄기는 숨겨진 게임에도 된다.
- `games.favorite_count`는 등록·해제와 같은 트랜잭션에서 실제로 추가·삭제된 행 수만큼만 바뀐다. 동시 요청은 UNIQUE `(user_id, game_id)`가 막는다.
- `GET /mypage/favorites`: 최근 등록 순, 페이지 단위. 항목은 `{game(게임 목록 항목과 같은 모양), release_alert, favorited_at}`이다. 공개되지 않은 게임은 목록에서 빠지지만 등록은 남는다.
- 탈퇴하면 관심 게임을 지우고 각 게임의 `favorite_count`를 줄인다.

### 7.8 이벤트·공지 (설계서에 없던 영역)

| Method | URL | 기능 | 인증 |
|---|---|---|---|
| **GET** | **/events** | 진행 중·예정 이벤트 | - |
| **GET** | **/events/{id}** | 이벤트 상세 (내 응모 여부) | - |
| **POST** | **/events/{id}/entries** | 응모 (포인트 차감) | O |
| **GET** | **/announcements** | 공지 목록 (고정 공지 먼저) | - |
| **GET** | **/announcements/{id}** | 공지 상세 | - |

## 8. 설계서 대비 변경 사항

| 대상 | 설계서 | 이 명세 | 이유 |
|---|---|---|---|
| 기본 주소 | `api.{도메인}` 서브도메인 | 웹과 같은 도메인 `/api/v1` | 하나의 Laravel 앱을 도메인 하나로 운영 (인증서·CORS 설정 단순) |
| 인증 | 이메일 가입·로그인, 비밀번호 재설정 | Google·Apple 소셜 로그인 + 가입 완료 단계 | ADR-003 |
| 홈페이지의 API 사용 | 홈페이지 Vue가 API 호출 | 홈페이지는 Inertia 웹 라우트, API는 Unity용 | ADR-002 |
| 게임 검색 | `GET /games/search` | `GET /games?q=` | 검색과 필터를 한 엔드포인트로 합침 |
| 오류 응답 | `success`, `message`, `errors` | `code` 추가 | 앱이 메시지 문구가 아닌 고정 코드로 분기 |
| 포인트 응답 | — | `data.point` | 출석·작성 직후 잔액을 바로 갱신 |
| `DELETE /reviews/{id}` | 삭제 또는 비공개 | 작성자 삭제만 | 숨김은 관리자 기능 (리뷰 정책 §5) |
| 추가 | — | 위 표에 굵게 표시한 엔드포인트 | 소셜 로그인·가입 완료·계정 연결·탈퇴·기기 토큰·댓글 수정/삭제·이벤트·공지 등 정책과 ERD에 필요한 기능 |

## 9. OpenAPI 문서

- 1단계에서 [Scramble](https://scramble.dedoc.co/)(`dedoc/scramble`)을 설치해 Laravel 코드(FormRequest, API Resource)에서 OpenAPI 문서를 **자동 생성**한다.
- 손으로 쓴 YAML은 코드와 쉽게 어긋나므로 따로 관리하지 않는다. 이 문서는 설계 기준으로 유지하고, 상세 스키마는 생성된 문서(`/docs/api`)를 본다.
- Unity 개발 시 생성된 OpenAPI 파일로 요청을 테스트한다.
- 개발 PC(`APP_ENV=local`)에서 `http://localhost:8000/docs/api`(화면), `/docs/api.json`(OpenAPI)으로 본다. 운영 환경에서는 열리지 않는다. 파일로 받으려면 `php artisan scramble:export`(→ `backend/api.json`).
- Scramble은 오류 응답을 Laravel 기본 형식으로 문서화하므로, `ApiErrorResponseTransformer`가 4xx·5xx 응답 본문을 §3의 실패 형식으로 바꾼다.

## 10. 결정 사항

2026-11-19에 다음과 같이 결정했다.

| # | 질문 | 결정 |
|---|---|---|
| 1 | API 주소를 웹과 같은 도메인으로 할 것인가? | **같은 도메인 `/api/v1`** (§8) |
| 2 | 토큰 유효기간은? | **60일**, 만료 시 재로그인. 1차는 리프레시 토큰 없음 |
| 3 | 오류 응답에 `code`를 추가할 것인가? | **추가한다** (§5) |
| 4 | §6 요청 제한 수치가 적절한가? | **제안값으로 시작**하고 운영하며 조정 |
| 5 | OpenAPI는 Scramble로 자동 생성할 것인가? | **자동 생성한다** (§9) |

**변경 이력:**
- 2026-11-19 ADR-002(Inertia)·ADR-003(소셜 로그인 전용) 결정에 따라 §2 인증, §5 오류 코드, §6 요청 제한, §7.1 인증 엔드포인트, §8을 수정했다.
- 2026-11-20 계정 연결을 구현하면서 §5에 `SOCIAL_PROVIDER_ALREADY_LINKED`를 추가했다 (`social_accounts`의 `user_id + provider` UNIQUE).
- 2026-11-20 공통 응답 형식을 구현하면서 §4에 400·405를, §5에 `METHOD_NOT_ALLOWED`·`BAD_REQUEST`를 추가했다. 프레임워크가 내는 모든 오류에도 `code`가 있어야 앱이 분기할 수 있기 때문이다. §9에 문서 주소를 적었다.
