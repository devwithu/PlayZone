# ADR-001: 저장소 구조

- 상태: 확정
- 결정일: 2026-11-19

## 배경

저장소 구조는 `apps/web`, `apps/mobile`, `backend/`를 따로 나누어 두었다. 그런데 기술 설계서(§2)는 홈페이지(Blade + Vue), REST API, Filament 관리자를 **하나의 Laravel 프로젝트**로 구성한다. 그래서 Laravel 앱을 어디에 둘지 정해야 했다.

## 결정

- Laravel 앱(웹·API·관리자 통합)은 `backend/`에 둔다.
- Unity 모바일 앱은 `apps/mobile/`에 둔다.
- `apps/web/`은 사용하지 않는다. 홈페이지는 `backend/resources/`에서 Blade + Vue로 개발한다.

## 이유

- 설계서의 단일 Laravel 구조를 그대로 따르면, 웹·API·관리자가 서비스 계층과 모델을 그대로 함께 쓸 수 있다.
- Blade + Vue 아일랜드 방식이라 별도 프론트엔드 프로젝트가 필요 없다.
- 1인 개발이므로 관리할 프로젝트 수를 최소화한다.

## 영향

- `php artisan`, `composer`, `npm` 명령은 `backend/`에서 실행한다.
- 이후 홈페이지를 별도 SPA로 분리하게 되면 `apps/web`을 다시 검토한다.
