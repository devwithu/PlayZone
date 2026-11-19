# ADR-003: 소셜 로그인 (Google·Apple 전용)

- 상태: **확정 (§7 미결 2건 제외)**
- 결정일: 2026-11-19
- 관련: [ADR-002 기술 스택](./ADR-002-tech-stack.md), [API 명세](../../03-architecture/api/api-spec.md) §2·§7.1, [ERD](../../03-architecture/database/erd.md) §3, [포인트 정책](../../01-planning/policies/point-policy.md)

## 1. 결정

- 웹과 Unity 앱 모두 **Google 로그인과 Apple 로그인만** 허용한다.
- 이메일·비밀번호 회원가입, 비밀번호 찾기·변경 기능은 **만들지 않는다**.
- 처음 소셜 로그인하면 계정이 만들어지고, **약관 동의 + 닉네임 설정**을 마쳐야 가입이 완료된다.

## 2. 이유

- 비밀번호 저장·재설정·이메일 인증 기능이 사라져 개발량과 보안 부담이 줄어든다.
- Google·Apple이 이메일을 인증한 계정만 들어오므로 별도 이메일 인증이 필요 없다.
- iOS 앱에서 Google 같은 외부 로그인을 제공하면 Apple 로그인(또는 동등한 방식)도 제공해야 한다는 App Store 심사 기준을 함께 충족한다.

## 3. 로그인 흐름

**웹 (Inertia)**
1. 사용자가 "Google로 로그인" / "Apple로 로그인" 버튼을 누른다.
2. Laravel Socialite로 제공자 로그인 화면으로 이동했다가 콜백으로 돌아온다 (`/auth/{provider}/redirect`, `/auth/{provider}/callback`).
3. 서버가 소셜 계정을 찾거나 새로 만들고 세션 로그인한다.
4. 가입이 완료되지 않았으면 가입 완료 화면(약관·닉네임)으로 보낸다.

**Unity 앱**
1. 앱이 Google·Apple 네이티브 로그인 SDK로 로그인하고 **ID 토큰**(Apple은 authorization code도)을 받는다.
2. `POST /api/v1/auth/social/{provider}`로 ID 토큰을 보낸다.
3. 서버가 ID 토큰의 **서명, 발급자(iss), 대상(aud), 만료(exp)**를 검증한다. 앱이 보낸 이메일·이름 값은 믿지 않고 토큰 안의 값만 쓴다.
4. 서버가 Sanctum 토큰을 발급한다. 응답에 `signup_completed: false`면 앱이 가입 완료 화면을 띄운다.

## 4. 계정 규칙

| 항목 | 규칙 |
|---|---|
| 계정 식별 | 제공자 + 제공자 사용자 ID(`sub`)로 식별한다. 이메일로 식별하지 않는다 |
| 이메일 | 제공자가 준 이메일을 저장한다. Apple "나의 이메일 가리기"면 중계 주소(`@privaterelay.appleid.com`)가 저장된다. Apple은 **첫 로그인 때만** 이메일을 주므로 그때 저장한다 |
| 이메일 중복 | 다른 제공자로 같은 이메일이 들어와도 **자동으로 합치지 않는다** (§7-1) |
| 가입 완료 | 약관·개인정보 처리방침 동의(필수), 마케팅 수신 동의(선택), 닉네임 설정 |
| 가입 미완료 계정 | 상태 `pending`. 로그인·로그아웃·가입 완료·탈퇴만 할 수 있다. 7일이 지나면 스케줄러가 삭제한다 |
| 가입 포인트 | 가입 완료 시 지급 (포인트 정책 §3) |

## 5. 회원 탈퇴

- 앱과 웹 모두 **앱 안에서 탈퇴**할 수 있어야 한다 (App Store 심사 기준 5.1.1(v)).
- 탈퇴 시 **Apple 로그인 계정은 Apple REST API(`/auth/revoke`)로 토큰을 폐기**한다. 이를 위해 Apple 로그인 때 받은 refresh token을 암호화해 저장한다.
- Google 계정은 Google 토큰 폐기 엔드포인트를 호출한다 (실패해도 탈퇴는 진행).
- 탈퇴 후 처리(익명화, 포인트 소멸)는 ERD §12, 포인트 정책 §10을 따른다.
- **재가입 제한:** 탈퇴 후 30일 동안 같은 소셜 계정(제공자 + `sub`)으로는 다시 가입할 수 없다. 이메일이 아니라 소셜 계정 식별자의 해시를 보관한다.

## 6. 관리자 로그인

- Filament 관리자도 같은 회원 계정(Google·Apple)으로 로그인하고, `roles`로 권한을 확인한다.
- 관리자 화면은 별도 로그인 화면 없이, 웹에서 로그인한 상태에서 관리자 역할이 있으면 접근을 허용한다.
- 설계서 §9의 "관리자 2단계 인증 검토"는 Google·Apple 계정 자체의 2단계 인증으로 대신한다. 관리자 계정은 2단계 인증을 켠 Google 계정만 쓰도록 운영 규칙으로 정한다.

## 7. 미결 사항

| # | 질문 | 제안 |
|---|---|---|
| 1 | 한 사람이 Google·Apple 두 계정을 하나로 쓰게 할 것인가? | 1차는 **자동 연결 없음**. 마이페이지에서 로그인한 상태로 다른 제공자를 **직접 연결**하는 기능을 1차에 포함한다 (앱은 iPhone에서 Apple, 웹은 Google로 쓰는 경우 대비) |
| 2 | 만 14세 미만 가입은? | 가입 완료 화면에서 "만 14세 이상입니다" 확인을 필수로 받고, 14세 미만은 가입을 받지 않는다 (법정대리인 동의 절차를 만들지 않기 위함) |

## 8. 준비할 것 (0단계 개발자 계정 등록과 함께)

- Google Cloud Console: OAuth 클라이언트 ID (웹, Android, iOS)
- Apple Developer: App ID에 Sign in with Apple 활성화, 웹용 Services ID, 로그인용 Key(.p8)
- 웹 콜백 URL 등록 (개발·운영 도메인)

## 참고 자료

- [Apple TN3194: 계정 삭제와 Sign in with Apple 토큰 폐기](https://developer.apple.com/documentation/technotes/tn3194-handling-account-deletions-and-revoking-tokens-for-sign-in-with-apple.md)
- [Apple: 앱 내 계정 삭제 요구사항](https://developer.apple.com/news?id=12m75xbj)
