# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Language

**사용자와의 모든 대화는 한국어로 한다.** (Always respond to the user in Korean.)

## Development environment

The user works on macOS at home and Windows (WSL2) at work. See `docs/04-development/environment-setup.md`.

- PostgreSQL 18 and Redis 8 run in Docker from the repo root: `docker compose up -d` (`stop` keeps data, `down -v` wipes it and re-runs `docker/postgres/init/`). Host ports are **5433** (Postgres) and **6380** (Redis), because 5432/6379 are used by another project's containers on this Mac. Never stop or touch the `girlsidle-*` containers.
- DBs are `game_evaluation` (dev) and `game_evaluation_test` (tests), user `game_user` / `secret`, both with `pg_trgm`.
- PHP 8.5, Composer and Node 26 are installed on the host (inside WSL2 on Windows), not in Docker.
- Line endings are LF everywhere (`.gitattributes`, `.editorconfig`). Prefer composer/npm scripts over bash-only scripts so commands work on both OSes. File-name casing must match class names exactly, because macOS is case-insensitive but Linux/WSL is not.

## Commands (run from `backend/`)

| Task | Command |
|---|---|
| First setup on a new machine | `cp .env.example .env && composer install && php artisan key:generate && php artisan migrate && npm ci` |
| Run everything for development | `composer dev` (server on :8000, queue worker, `pail` logs, Vite) |
| Full check (Pint check + PHPStan + tests) | `composer test` |
| Tests only | `php artisan test` |
| One test file / one test | `php artisan test tests/Feature/ExampleTest.php` / `php artisan test --filter=test_name` |
| PHP format / check | `composer lint` / `composer lint:check` |
| PHP static analysis (Larastan) | `composer types:check` |
| JS/Vue lint + format check / fix | `npm run check` / `npm run check:fix` (Vite+ `vp`) |
| TS type check | `npm run types:check` |
| Build with SSR | `npm run build:ssr`, then `php artisan inertia:start-ssr` (stop: `inertia:stop-ssr`) |
| Regenerate typed routes for TS | `php artisan wayfinder:generate` (the Vite plugin also does it) |

Tests run against the PostgreSQL test DB `game_evaluation_test` (set in `phpunit.xml`), not SQLite, so Docker must be running. `composer dev` does not start the SSR server. SSR only applies to built assets, so to check server-rendered HTML, build with `build:ssr` and run `inertia:start-ssr`.

## Current state

- `backend/` holds the Laravel 13 app, created from the official Vue starter kit (`laravel/vue-starter-kit` `dev-main`): Inertia 3, Vue 3, TypeScript, Tailwind 4, shadcn-vue (reka-ui), Fortify, Wayfinder (typed routes in `resources/js/routes`, `resources/js/actions`) and Vite+ (`vite-plus`). It is connected to Docker Postgres/Redis (`predis` client, cache and queue on Redis, `public` disk). SSR was verified on 2026-11-19.
- Auth (ADR-003): the kit's password/registration/reset/verification/2FA/passkey code is removed. Fortify stays installed with `features => []` and `Fortify::ignoreRoutes()` so email/password signup can be added later (ADR-003 §8; the originals are in commit 9c6de37). Web login is Google/Apple via Socialite (`SocialLoginController`, `SocialLoginService`), and a first login creates a `pending` user who must finish `/signup/complete`. Member pages use the `signup.completed` middleware. Tests fake providers with `Socialite::fake()`, so no real OAuth keys are needed. Not done yet: the API ID-token login (Sanctum), account linking and withdrawal.
- Feature tests that render an Inertia page need a current Vite manifest. After adding a page, run `npm run build` (or `build:ssr`), or the test fails with "Unable to locate file in Vite manifest".
- `backend/.github/workflows/tests.yml` comes from the kit and is inactive, because GitHub only reads the repo-root `.github/`.

Original design doc: `docs/00-overview/game-evaluation-development-design.md`. Identical copies exist at `docs/game-evaluation-development-design.md` and `untitled folder/`. The confirmed docs listed below override it.

## Product: 게임평가단 (game evaluation community)

A platform for game info, release schedules, user reviews/ratings, community boards, attendance check-in, points, rankings and events. It has three clients on **one Laravel backend and one PostgreSQL database**:

| Client | Tech | Auth |
|---|---|---|
| Website | Inertia + Vue 3 + TypeScript with SSR (official Laravel Vue starter kit) | Laravel session via Socialite (Google/Apple) |
| Admin | Filament (`/admin`) | Filament auth + role permissions |
| Mobile app | Unity C# (Android/iOS) | Google/Apple ID token → Sanctum Bearer token |

**Login is Google and Apple only** (`ADR-003-social-login.md`): there are no passwords, email/password signup, password reset or email verification, so remove Fortify's email/password features from the starter kit. A first social login creates a `pending` user, who must finish signup (terms + nickname) before using other features.

Supporting services: Redis (cache/queue), Laravel Reverb (websocket notifications to the web), Firebase FCM (mobile push), file storage. Files are stored on the app server first (Laravel `public` disk) and will later move to S3 or Cloudflare R2. Always store **relative paths** in the DB (`*_path`) and build URLs with `Storage::url()`, never store full URLs, so the switch only needs a disk config change. Versions (`ADR-002-tech-stack.md`): PHP 8.5, Laravel 13.x, Inertia 3, Filament 5.x, PostgreSQL 18 (Docker), Redis 8 (Docker), PHPUnit (kit default), Pint, Larastan.

### Repository layout (decided, see `docs/07-meetings/decisions/ADR-001-repo-structure.md`)

- `backend/`: the **single Laravel project**. It contains the website (Inertia pages in `resources/js/pages`), the API (`app/Http/Controllers/Api/V1`) and the Filament admin, following the folder structure in design doc §2. Run all `php artisan`, `composer` and `npm` commands from `backend/`.
- `apps/mobile/`: the Unity project.
- `apps/web/` is **not used**. Do not put website code there.

The project has one developer (solo), so plan work sequentially. See `docs/00-overview/roadmap.md`.

### Architectural rules from the design doc

- **Thin controllers, shared services.** Business logic lives in `app/Services/*Service.php` (Game, Review, Attendance, Point, Notification) and is reused by Web controllers, `Api/V1` controllers and Filament. Validation goes in FormRequests (`app/Http/Requests/Api/V1`) and authorization in Policies.
- **Unity is only an API client.** It never touches the DB and never scrapes HTML. All mobile access goes through versioned REST at `/api/v1/`.
- **API contract:** `docs/03-architecture/api/api-spec.md` is the source of truth. Base path is `/api/v1` on the same domain as the website. The API is for Unity (Sanctum Bearer tokens, 60-day expiry). The website does **not** call `/api/v1`. It uses Inertia web routes, and web and API controllers share the same services and validation rules. Responses use `{ "success", "message", "data" }`, with paginated lists under `data.items` + `data.pagination`. Errors are `{ "success": false, "code", "message", "errors" }`, where `code` is a fixed string from the spec's error-code table that clients branch on. Point-granting endpoints return `data.point`. OpenAPI docs are generated from code by Scramble, not hand-written.
- **Policies and schema:** `docs/01-planning/policies/point-policy.md`, `review-policy.md` and `docs/03-architecture/database/erd.md` are confirmed. They refine (and in places override) the original design doc. Follow them when implementing.
- **Points and attendance integrity:** the server decides dates and point amounts and never trusts client values. Attendance, point grants and review likes must use a DB transaction **plus** a UNIQUE constraint (e.g. `user_id + attendance_date`). All point changes go through `PointService`, which keeps a transaction ledger (`point_transactions`) and the user's balance consistent, using an idempotency/dedup key per source (attendance, review, event).
- **Events after commit:** broadcast events (e.g. `ReviewCreated` → Reverb → Vue, plus FCM push) only after the DB transaction commits.
- **Data conventions:** store timestamps in UTC and convert to KST for display. Review scores are 1–10 with a CHECK constraint. Prefer status changes and soft retention over hard deletes. Unique keys: email, game slug, `user_id+game_id` (favorites), `user_id+review_id` (review likes).
- Public pages (games, reviews, posts) must be SSR-rendered with title/meta tags in the first HTML response, because Naver and Google search traffic depends on it. Avoid browser-only APIs (`window`, `document`) at module top level in Vue pages. The design doc's Blade + Vue-islands approach (§3.3) is superseded by ADR-002.

Settle the ERD, API spec, point policy and review policy before implementing features (design doc §12).

## Documentation conventions

- Classify docs by **what kind of document it is**, not by when it was written. For example, an API spec belongs in `docs/03-architecture/api/` even if written during development. Folders are `00-overview` through `08-releases`.
- Write docs in Markdown. API specs may be OpenAPI YAML.
- Requirement IDs: `REQ-###` (common), `WEB-###`, `MOB-###`, `API-###`, `TC-###` (test cases), so they can be traced across docs.
- Meeting notes go in `docs/07-meetings/YYYY/YYYY-MM-DD-topic.md`. Architecture decisions go separately in `docs/07-meetings/decisions/ADR-###-topic.md`.

## Git conventions

- **Commit directly to `main`.** This is a solo project, so do not create branches or PRs unless the user asks. (The `develop`/`feature/*`/`docs/*` branch strategy in `git-project-documentation-structure.md` is not used.)
- Commit prefixes: `feat:`, `fix:`, `docs:`, `design:`, `test:`, `refactor:`. Messages are typically written in Korean, e.g. `docs: 요구사항 정의서 수정`.
