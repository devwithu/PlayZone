# 로드맵

> 작성 기준: 2026-11-19
> 근거 문서: [프로젝트 목표](./project-goals.md), [기술 개발 설계서](./game-evaluation-development-design.md)

## 전제

- **1인 개발**이므로 단계를 순서대로 진행한다. 단계끼리 병행하지 않는다.
- 착수일은 2026-11-23(월)로 가정한다.
- 설계서의 4단계 앞에 **0단계(착수 준비)**를 둔다. ERD, API 명세, 포인트 정책, 리뷰 정책을 개발 전에 확정하기 위해서다.

## 전체 일정

| 단계 | 내용 | 기간 | 주차 |
|---|---|---|---|
| 0단계 | 착수 준비 | 2주 | W1–W2 |
| 1단계 | 기반 구축 | 2~3주 | W3–W5 |
| 2단계 | 게임 정보 | 3~4주 | W6–W9 |
| 3단계 | 커뮤니티·포인트 | 4~6주 | W10–W15 |
| 4단계 | Unity·알림·테스트 | 4~6주 | W16–W21 |

총 15~21주이며, 늦어지면 2027년 4월 중순에 1차 오픈한다.

---

## 0단계: 착수 준비 (W1–W2)

**목표:** 코드를 쓰기 전에 바꾸기 어려운 결정을 끝낸다.

| 작업 | 산출물 | 상태 |
|---|---|---|
| 저장소 구조 결정 | `docs/07-meetings/decisions/ADR-001-repo-structure.md` | 완료 |
| 기술 스택 확정 (PHP 8.3, Laravel 13, Filament 5, PostgreSQL, Redis) | `docs/07-meetings/decisions/ADR-002-tech-stack.md` | 완료 |
| 소셜 로그인 방식 결정 (Google·Apple 전용) | `docs/07-meetings/decisions/ADR-003-social-login.md` | 완료 |
| 요구사항 정의 (REQ/WEB/MOB ID 부여) | `docs/01-planning/requirements/requirements.md` | 완료 |
| 포인트 정책 (지급·차감 기준, 중복 방지 키) | `docs/01-planning/policies/point-policy.md` | 완료 |
| 리뷰 정책 (게임당 리뷰 수, 공개 상태, 평균 반영) | `docs/01-planning/policies/review-policy.md` | 완료 |
| ERD (9개 영역 테이블·제약·인덱스) | `docs/03-architecture/database/erd.md` | 완료 |
| API 명세 (`/api/v1`, 응답 규격, 오류 코드) | `docs/03-architecture/api/api-spec.md` (OpenAPI는 1단계에서 자동 생성) | 완료 |
| 주요 화면 와이어프레임 | `docs/02-design/wireframes/screens.md` | 완료 |
| Apple·Google 개발자 계정 등록 | 기존 계정 사용 | 완료 |

## 1단계: 기반 구축 (W3–W5)

- [x] `backend/`에 Laravel 공식 Vue 스타터 키트로 프로젝트 생성 (PHP 8.5, Laravel 13.43), PostgreSQL·Redis 연결 (2026-11-19)
- [x] 이메일·비밀번호 인증(Fortify 비밀번호·가입·재설정·이메일 인증·2FA·패스키) 제거 (2026-11-19, 나중에 추가할 수 있게 비워 둠, ADR-003 §8)
- [x] **Inertia SSR 확인 (2026-11-19, 시작 페이지로 확인. 게임 상세는 2단계에서 재확인):** 게임 상세 페이지 첫 HTML에 본문·메타 태그가 들어가는지 검증 (실패 시 Blade 방식으로 전환 결정, ADR-002)
- **소셜 로그인 키 발급** (로그인 구현 직전): Google OAuth 클라이언트 ID(웹·Android·iOS), Apple Sign in with Apple(App ID, Services ID, .p8 키), 콜백 URL 등록 (ADR-003 §9)
- [x] 회원·권한·소셜 계정 마이그레이션, 웹 Google·Apple 로그인(Socialite), 가입 완료 화면(만 14세 확인·약관·닉네임), 로그아웃 (2026-11-19, 가짜 로그인 응답으로 테스트, 실제 키로는 미확인)
- [x] 앱 소셜 로그인 API `POST /api/v1/auth/social/{provider}` (2026-11-20): ID 토큰 검증(서명·iss·aud·exp), Sanctum 토큰 발급, Apple authorization code → refresh token 저장. 테스트용 키로 검증, 실제 키로는 미확인
- [x] 앱 인증 API: 가입 완료, 닉네임 확인, 내 정보, 로그아웃 (2026-11-20, api-spec §7.1. 등급·제재·가입 포인트는 3단계)
- [ ] 마이페이지 계정 연결, 탈퇴 (ADR-003)
- Filament 설치, 회원 관리 화면
- [x] 공통 API 응답 형식 (2026-11-20): `/api/v1` 라우트, Sanctum(토큰 60일), 성공·목록·오류 응답, 오류 코드 enum, 기본 요청 제한, Scramble OpenAPI(`/docs/api`). 규칙은 `docs/04-development/coding-conventions.md` §1
- 테스트 환경, GitHub Actions CI
- 개발 환경 문서: `docs/04-development/environment-setup.md`

**완료 기준:** 웹과 API 양쪽에서 같은 Google·Apple 계정으로 로그인할 수 있고, 게임 상세 페이지가 SSR로 렌더링된다.

## 2단계: 게임 정보 (W6–W9)

- 게임·장르·플랫폼·출시일 테이블, 관리자 게임 등록
- 게임 목록·상세·검색·필터 (웹 + API)
- 출시 일정 화면·API, 관심 게임

**완료 기준:** 관리자가 등록한 게임을 웹과 API에서 검색하고 조회할 수 있다.

## 3단계: 커뮤니티·포인트 (W10–W15)

- `PointService` 원장 구조를 먼저 만들고, 이후 기능은 모두 이 서비스를 통해 포인트를 처리한다.
- 출석 (트랜잭션 + UNIQUE 제약, 동시 요청 테스트)
- 리뷰·별점·추천·댓글, 평균 점수 반영, 작성 포인트 지급
- 게시판·댓글, 마이페이지, 기본 랭킹
- 관리자 리뷰·게시글 관리, 신고 검토 대기열
- 기본 이벤트(관리자 등록, 포인트 응모, 당첨 지정), 공지사항

**완료 기준:** 출석과 리뷰 작성 포인트가 정확히 한 번만 지급되고, 이를 테스트로 증명한다.

## 4단계: Unity·알림·테스트 (W16–W21)

- `apps/mobile/`에서 Unity Google·Apple 네이티브 로그인, 토큰 저장, 핵심 API 연동 (커뮤니티 제외)
- FCM 앱 푸시 (DB 저장이 끝난 뒤 발송). 웹 실시간 알림(Reverb)은 1차 이후
- QA 체크리스트, 보안 점검 (요청 제한, 업로드 검증, 관리자 권한)
- 운영 서버 구성, 백업과 복구 테스트, 앱 스토어 심사 제출

**완료 기준:** [1차 오픈 필수 기능](./project-goals.md#3-1차-오픈-목표-필수-기능) 10개를 모두 체크한다.

---

## 일정 위험과 대응 (1인 개발)

| 위험 | 대응 |
|---|---|
| 4단계에 Unity 작업과 앱 심사가 몰림 | 2~3단계 API는 완성 즉시 API 테스트로 검증해, Unity 연동 때 백엔드 수정을 줄인다 |
| 앱 스토어 심사 반려·지연 | 개발자 계정을 0단계에 등록하고, 심사 제출은 W19 전후로 여유를 둔다 |
| 일정 초과 | 이미 P2로 미룬 기능(랭킹 고도화, 웹 실시간 알림, 앱 커뮤니티) 외에, 필요하면 이벤트 관리 기능을 최소화한다 |
