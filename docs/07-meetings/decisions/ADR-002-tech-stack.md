# ADR-002: 기술 스택

- 상태: **확정**
- 결정일: 2026-11-19
- 관련: [ADR-001 저장소 구조](./ADR-001-repo-structure.md), [ADR-003 소셜 로그인](./ADR-003-social-login.md), [기술 개발 설계서](../../00-overview/game-evaluation-development-design.md) §1·§3, [API 명세](../../03-architecture/api/api-spec.md), [ERD](../../03-architecture/database/erd.md)

## 1. 배경

설계서는 홈페이지를 **Laravel Blade + Vue**(Blade 페이지에 Vue 컴포넌트를 부분적으로 끼워 넣는 방식)로 정했다. 이번에 **Laravel + Inertia + Vue** 방식도 함께 검토했다. 나머지 스택은 설계서와 0단계 결정을 정리하고, 2026-11 기준 버전 호환성을 확인했다.

## 2. 결정

1. 홈페이지는 **Inertia + Vue 3 + TypeScript + SSR**로 개발한다 (방식 B).
2. PHP는 **8.5**를 사용한다.
3. 로그인은 **Google·Apple 소셜 로그인만** 허용한다 (상세는 ADR-003).

## 3. 홈페이지 프론트엔드: 두 방식 비교

### 방식 A: Blade + Vue 아일랜드 (설계서)
페이지는 Blade로 서버에서 HTML을 만든다. 별점, 관심 게임, 리뷰 작성 같은 상호작용 부분만 Vue 컴포넌트로 붙이고, 이 컴포넌트가 `/api/v1`을 세션 인증으로 호출한다.

### 방식 B: Inertia + Vue + SSR (채택)
모든 화면을 Vue 페이지 컴포넌트로 만든다. Laravel 컨트롤러가 `Inertia::render('Games/Show', [...])`로 데이터를 넘기면, Inertia가 SPA처럼 화면을 바꾼다. 라우팅·인증·검증은 Laravel이 그대로 맡는다. 검색 노출을 위해 SSR(Node 프로세스가 첫 HTML을 렌더링)을 켠다.

### 비교

| 기준 | A. Blade + Vue 아일랜드 | B. Inertia + Vue + SSR |
|---|---|---|
| **검색 노출 (네이버·구글)** | 기본이 서버 렌더링이라 별도 작업 없음 | SSR을 켜야 함. 켜면 첫 HTML이 서버에서 만들어져 수집 가능 |
| **시작 비용** | 레이아웃·UI 컴포넌트를 직접 구성 | Laravel 공식 Vue 스타터 키트가 레이아웃, 설정 화면, UI 컴포넌트(shadcn-vue), TypeScript 환경을 제공 |
| **화면 개발 방식** | Blade 템플릿과 Vue 컴포넌트 **두 가지**를 오가며 개발. 컴포넌트를 붙이는 연결 코드 필요 | Vue **한 가지**로 모든 화면 개발 |
| **상호작용이 늘어날 때** | 헤더 알림·로그인 상태처럼 여러 컴포넌트가 상태를 공유하면 복잡해짐 | 공유 데이터(`HandleInertiaRequests`)로 로그인 사용자·알림 수를 모든 페이지에 전달 |
| **사용자 체감** | 페이지 이동마다 전체 새로고침 | 페이지 이동이 SPA처럼 부드러움 |
| **API 재사용** | 웹의 Vue 위젯이 `/api/v1`을 그대로 사용 | 웹은 Inertia 라우트 사용. `/api/v1`은 Unity용 → 웹용·API용 **컨트롤러가 둘** (서비스·검증 규칙은 공유) |
| **운영** | PHP만 실행 | SSR용 **Node 프로세스**를 추가로 실행·관리 (`php artisan inertia:start-ssr`, 프로세스 관리자에 등록) |
| **SSR 주의점** | 해당 없음 | 서버 렌더링 시 `window` 등 브라우저 전용 코드 사용 주의, 하이드레이션 불일치 디버깅 |
| **Filament 관리자** | 영향 없음 (Filament는 Livewire 기반으로 독립) | 영향 없음 |
| **Reverb 실시간 알림** | Laravel Echo로 사용 가능 | Laravel Echo로 사용 가능 |

### 검색 노출

- 네이버는 SPA의 콘텐츠 수집을 위해 SSR 도입을 권장한다는 자료가 여러 곳에 있다(네이버 공식 원문은 확인하지 못함). 게임 상세·리뷰 페이지는 검색 유입의 핵심이므로 **SSR은 필수**다.
- 메타 태그(제목·설명·OG 이미지)가 **첫 HTML 응답에 포함**되어야 한다. Inertia `<Head>`를 SSR로 렌더링해서 충족한다.

### 채택 이유

1인 개발에서 가장 큰 비용은 **개발 시간**이다. 화면 기술을 Vue 하나로 통일하면 화면이 늘어날수록 유지보수가 쉽고, 공식 스타터 키트의 레이아웃·UI 컴포넌트로 시작 비용을 줄인다. 대가는 SSR Node 프로세스 운영과 웹·API 컨트롤러 이중화다. 컨트롤러는 얇게 두고 로직은 서비스 계층에 있으므로(설계서 원칙) 이중화 부담은 작다.

> 소셜 로그인만 쓰기로 하면서(ADR-003), 스타터 키트의 이메일·비밀번호 인증 화면(Fortify)은 쓰지 않는다. 처음 검토 때 B의 장점으로 꼽았던 "인증 화면 일체 제공"은 효과가 줄었지만, 위 이유로 B를 유지한다.

**조건:** 1단계에서 게임 상세 페이지의 SSR 결과(첫 HTML에 본문·메타 태그 포함)를 확인한다. 문제가 있으면 방식 A로 되돌리는 결정을 1단계 안에 내린다.

## 4. 전체 기술 스택

| 영역 | 선택 | 버전 기준 | 비고 |
|---|---|---|---|
| 언어 | PHP | **8.5** | Laravel 13은 PHP 8.3~8.5 지원 |
| 백엔드 | Laravel | 13.x | 2026-03 출시 |
| 홈페이지 | Inertia + Vue 3 + TypeScript | 공식 Vue 스타터 키트 기본 버전 | Composition API, Tailwind CSS, shadcn-vue. Inertia 버전은 설치 시 확인 |
| SSR | Inertia SSR (Node) | | 게임·리뷰·게시글 등 공개 페이지 검색 노출 |
| 로그인 | Laravel Socialite (Google, Apple) | | 웹은 리디렉션 방식, 앱은 ID 토큰 검증 (ADR-003). 스타터 키트의 Fortify 이메일 인증은 제거 |
| API 인증 | Laravel Sanctum | | Unity 토큰 60일 (API 명세 §2) |
| 관리자 | Filament | 5.x | PHP 8.2+, Laravel 11.28+, Livewire 4, Tailwind 4.1+ 요구 → 충족. 관리자 로그인 방식은 ADR-003 |
| DB | PostgreSQL | 17 이상 | `pg_trgm` 확장 사용 (ERD §12) |
| 캐시·큐 | Redis | 7 이상 | 캐시, 큐. 세션은 DB(`SESSION_DRIVER=database`) |
| 실시간 | Laravel Reverb + Echo | | 웹 실시간 알림 |
| 푸시 | Firebase Cloud Messaging | | Laravel용 Firebase 패키지는 4단계에 선정 |
| 파일 | 서버 로컬 저장(`public` 디스크) → S3 또는 Cloudflare R2 | | DB에는 상대 경로 (ERD §1) |
| API 문서 | Scramble (`dedoc/scramble`) | | OpenAPI 자동 생성 (API 명세 §9) |
| 테스트 | Pest | | Laravel 기본 |
| 코드 스타일 | Laravel Pint | | |
| 정적 분석 | Larastan | | 1단계에서 레벨 결정 |
| Node.js | LTS | | Vite 빌드, SSR |
| 모바일 | Unity (C#) | Unity 6 LTS | Google·Apple 로그인 플러그인 포함, 4단계에서 세부 버전 확정 |

### PHP 8.5 주의점

- 서드파티 패키지가 PHP 8.5를 지원하는지 1단계 설치 때 확인한다. 지원하지 않는 패키지가 핵심 기능에 있으면 PHP 8.4로 내린다.
- 운영 서버(호스팅)가 PHP 8.5를 제공하는지 배포 환경을 정할 때 확인한다.

## 5. 이 결정으로 바뀐 문서

| 문서 | 변경 |
|---|---|
| API 명세 §2 | 홈페이지는 Inertia 웹 라우트, `/api/v1`은 Unity용. 인증은 소셜 로그인 |
| ADR-001 | 홈페이지는 `backend/resources/js/pages/`의 Inertia 페이지 |
| CLAUDE.md | 프론트엔드·인증 설명 수정 |
| 로드맵 1단계 | 스타터 키트로 생성 + SSR 확인 + 소셜 로그인 |

## 참고 자료

- [Laravel 13 Starter Kits 문서](https://laravel.com/docs/13.x/starter-kits.md)
- [Laravel 13 Release Notes](https://laravel.com/docs/13.x/releases.md)
- [Laravel 지원 버전 (endoflife.date)](https://www.endoflife.date/laravel)
- [Filament 설치 요구사항](https://filamentphp.com/docs/introduction/installation)
- [Filament 5 업그레이드 가이드](https://filamentphp.com/docs/5.x/upgrade-guide.md)
- [Inertia SSR 문서 (v1)](https://inertiajs.com/docs/v1/advanced/server-side-rendering.md)
- [네이버 SEO 가이드 소개 (2차 자료)](https://inblog.ai/ko/blog/naver-official-seo-guide)
- [Google: 검색 관련 자바스크립트 문제 해결](https://developers.google.com/search/docs/guides/rendering?hl=ko)
