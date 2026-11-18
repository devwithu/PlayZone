# 웹사이트와 모바일 앱 프로젝트의 Git 문서 관리 구조

## 질문

웹사이트와 모바일 앱을 개발하는 프로젝트를 진행할 예정이며, 모든 문서를
Git으로 관리하고 싶다. 폴더 구조를 어떻게 잡는 것이 좋을까?

## 1. 추천 Git 폴더 구조

웹사이트와 모바일 앱을 함께 개발한다면, 소스 코드와 프로젝트 문서를
하나의 Git 저장소(Monorepo)에서 통합 관리하는 방식을 추천한다.

프로젝트 공통 문서, 플랫폼별 문서, 개발 산출물, 기술 문서를 구분해 확장
가능한 구조로 설계한다.

``` text
my-project/
│
├── README.md
├── CHANGELOG.md
├── CONTRIBUTING.md
├── .gitignore
├── .gitattributes
│
├── docs/                       # 프로젝트 전체 문서
│   ├── 00-overview/             # 프로젝트 개요
│   │   ├── project-overview.md
│   │   ├── project-goals.md
│   │   ├── scope.md
│   │   └── roadmap.md
│   │
│   ├── 01-planning/             # 기획 문서
│   │   ├── requirements/
│   │   ├── user-stories/
│   │   ├── business-rules/
│   │   └── policies/
│   │
│   ├── 02-design/               # 디자인 문서
│   │   ├── ui-ux/
│   │   ├── wireframes/
│   │   ├── prototypes/
│   │   └── design-system/
│   │
│   ├── 03-architecture/         # 시스템 설계
│   │   ├── system-overview.md
│   │   ├── architecture.md
│   │   ├── database/
│   │   ├── api/
│   │   └── diagrams/
│   │
│   ├── 04-development/          # 개발 가이드
│   │   ├── coding-conventions.md
│   │   ├── git-conventions.md
│   │   ├── environment-setup.md
│   │   └── deployment.md
│   │
│   ├── 05-testing/              # 테스트 문서
│   │   ├── test-strategy.md
│   │   ├── test-cases/
│   │   ├── qa-checklist.md
│   │   └── bug-reports/
│   │
│   ├── 06-operations/           # 운영 문서
│   │   ├── monitoring.md
│   │   ├── incident-response.md
│   │   ├── backup-recovery.md
│   │   └── maintenance.md
│   │
│   ├── 07-meetings/             # 회의록
│   │   ├── 2026/
│   │   └── decisions/
│   │
│   └── 08-releases/             # 배포 기록
│       ├── web/
│       └── mobile/
│
├── apps/                        # 애플리케이션 소스
│   ├── web/                     # 웹사이트
│   │   ├── src/
│   │   ├── public/
│   │   └── README.md
│   │
│   └── mobile/                  # 모바일 앱
│       ├── src/
│       ├── android/
│       ├── ios/
│       └── README.md
│
├── backend/                     # 서버 및 백엔드
│   ├── src/
│   ├── migrations/
│   └── README.md
│
├── shared/                      # 공통 리소스
│   ├── types/
│   ├── constants/
│   ├── validation/
│   └── assets/
│
├── scripts/                     # 자동화 스크립트
│
└── .github/                     # GitHub 설정
    ├── workflows/
    ├── ISSUE_TEMPLATE/
    └── PULL_REQUEST_TEMPLATE.md
```

## 2. 문서별 관리 기준

  문서 종류         저장 위치                주요 내용
  ----------------- ------------------------ -------------------------
  프로젝트 개요     `docs/00-overview`       목표, 범위, 일정
  요구사항 정의서   `docs/01-planning`       기능, 정책, 사용자 요구
  화면 설계서       `docs/02-design`         화면 구성, UI/UX
  시스템 설계서     `docs/03-architecture`   시스템 구조, DB, API
  개발 가이드       `docs/04-development`    코딩 규칙, Git 규칙
  테스트 문서       `docs/05-testing`        테스트 케이스, QA
  운영 매뉴얼       `docs/06-operations`     모니터링, 장애 대응
  회의록            `docs/07-meetings`       회의 내용, 결정 사항
  배포 이력         `docs/08-releases`       버전별 배포 내역

문서는 업무 단계가 아니라 **문서의 성격**을 기준으로 분류한다. 예를 들어
API 명세서는 개발 단계에서 작성하더라도 시스템 설계 문서이므로
`03-architecture/api`에 저장한다.

## 3. 문서 작성 및 관리 규칙

### 3.1 Markdown을 기본 문서 형식으로 사용

가능하면 `.md` 파일로 작성한다.

  문서              추천 형식
  ----------------- ----------------------------
  기획서            Markdown
  요구사항 정의서   Markdown
  API 명세서        Markdown 또는 OpenAPI YAML
  DB 설계서         Markdown + ERD
  회의록            Markdown
  테스트 케이스     Markdown 또는 CSV
  화면 설계서       Markdown + 이미지

예시 파일: `docs/01-planning/requirements/user-authentication.md`

``` markdown
# 사용자 인증 기능

## 1. 개요
사용자의 회원가입 및 로그인을 제공한다.

## 2. 기능 요구사항

| ID | 기능 | 설명 |
|---|---|---|
| AUTH-001 | 회원가입 | 신규 사용자 등록 |
| AUTH-002 | 로그인 | 사용자 인증 |
| AUTH-003 | 로그아웃 | 인증 세션 종료 |

## 3. 예외 처리
- 중복 계정 가입 제한
- 잘못된 비밀번호 처리

## 4. 관련 API
- POST /api/auth/signup
- POST /api/auth/login
- POST /api/auth/logout
```

### 3.2 요구사항마다 고유 ID 부여

요구사항에 고유 ID를 부여하면 요구사항, 화면, API, 테스트 케이스 간의
추적이 쉬워진다.

  구분     ID 규칙   예시
  -------- --------- ---------
  공통     REQ       REQ-001
  웹       WEB       WEB-001
  모바일   MOB       MOB-001
  API      API       API-001
  테스트   TC        TC-001

### 3.3 회의록과 의사결정 기록 분리

회의록에 모든 의사결정 내용을 포함하면 나중에 중요한 결정을 찾기 어렵다.
회의 기록과 주요 기술 의사결정 기록을 분리한다.

``` text
docs/07-meetings/
├── 2026/
│   ├── 2026-11-19-kickoff.md
│   ├── 2026-11-20-planning.md
│   └── 2026-11-21-development.md
│
└── decisions/
    ├── ADR-001-tech-stack.md
    ├── ADR-002-authentication.md
    └── ADR-003-database-selection.md
```

ADR(Architecture Decision Record)은 중요한 기술적 의사결정과 그 배경,
선택 이유를 기록하는 문서다.

## 4. Git 브랜치 및 커밋 관리

문서도 소스 코드와 동일하게 브랜치와 커밋을 관리한다.

### 브랜치 전략

  브랜치        용도
  ------------- --------------------
  `main`        안정적인 기준 버전
  `develop`     개발 통합
  `feature/*`   기능 개발
  `docs/*`      문서 작성 및 수정
  `hotfix/*`    긴급 수정

브랜치 생성 예시:

``` bash
git checkout -b docs/update-requirements
```

### 커밋 메시지 규칙

``` text
feat: 회원가입 기능 추가
fix: 로그인 오류 수정
docs: 요구사항 정의서 수정
design: 로그인 화면 설계 변경
test: 회원가입 테스트 케이스 추가
refactor: 인증 모듈 리팩토링
```

문서 변경도 Pull Request를 통해 리뷰하면 문서 품질을 유지하는 데 도움이
된다.

## 5. 추천 문서 관리 도구

### GitHub

-   Git 저장소, 문서 버전 관리, 이슈, Pull Request, 코드 리뷰를 통합
    관리한다.
-   공식 사이트: https://github.com/

### Mermaid

-   Markdown 안에서 시스템 구성도, 시퀀스 다이어그램, 플로우차트 등을
    텍스트로 관리할 수 있다.
-   공식 사이트: https://mermaid.js.org/

### OpenAPI

-   YAML 기반으로 API 명세를 관리하고 Swagger UI 등을 통해 문서화할 수
    있다.
-   공식 문서: https://swagger.io/specification/

## 6. 최종 추천 운영 프로세스

1.  **기획**: 요구사항 정의서, 정책서, 사용자 시나리오 작성
2.  **설계**: 화면 설계, DB 설계, API 명세 작성
3.  **개발**: Web, Mobile, Backend 구현
4.  **테스트**: 테스트 케이스, QA, 버그 관리
5.  **배포 및 운영**: 배포 이력, 운영 매뉴얼, 장애 대응 관리

모든 단계의 산출물을 Git으로 버전 관리한다. 이를 통해 변경 이력, 리뷰,
협업, 복구를 일관되게 처리할 수 있다.

## 7. 프로젝트 구조를 확정하기 위해 확인할 사항

실제 프로젝트에 맞춰 구조를 확정할 때는 다음 항목을 결정한다.

1.  **웹사이트와 모바일 앱 개발 방식**
    -   웹과 모바일을 각각 별도 개발
    -   React 기반 공통 기술 활용
    -   Flutter Web 및 모바일 통합
2.  **모바일 앱 개발 기술**
    -   Flutter
    -   React Native
    -   Kotlin / Swift 네이티브
    -   미정
3.  **백엔드 구성**
    -   별도 백엔드 서버
    -   Firebase / Supabase
    -   서버리스
    -   미정
4.  **개발팀 규모**
    -   1인 개발
    -   2\~5명
    -   6\~15명
    -   16명 이상

이 조건에 따라 실제 사용할 Git 폴더 구조와 문서 템플릿, 브랜치 규칙을
구체화한다.
