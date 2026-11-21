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
| First setup on a new machine | `cp .env.example .env && composer install && php artisan key:generate && php artisan migrate && php artisan storage:link && npm ci` |
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
- Auth (ADR-003): the kit's password/registration/reset/verification/2FA/passkey code is removed. Fortify stays installed with `features => []` and `Fortify::ignoreRoutes()` so email/password signup can be added later (ADR-003 §8; the originals are in commit 9c6de37). Web login is Google/Apple via Socialite (`SocialLoginController`, `SocialLoginService`), and a first login creates a `pending` user who must finish `/signup/complete`. Member pages use the `signup.completed` middleware. Tests fake providers with `Socialite::fake()`, so no real OAuth keys are needed. App login is `POST /api/v1/auth/social/{provider}` (`AppLoginService`): `SocialAuth\IdTokenVerifier` checks the ID token against Google/Apple JWKS (signature, iss, aud from `services.*.id_token_audiences`, exp), then reuses `SocialLoginService` and issues a Sanctum token per `device_name`; `SocialAuth\AppleTokenClient` swaps Apple's authorization code for a refresh token. Tests sign fake ID tokens with `Tests\Concerns\FakesSocialIdTokens` and `Http::fake`. On `/api/*`, the `signup.completed` middleware throws `SIGNUP_INCOMPLETE`/`ACCOUNT_SUSPENDED` instead of redirecting. The other app auth endpoints (`/auth/me`, `/auth/signup/complete`, `/auth/nickname-check`, `/auth/logout`) are in `Api\V1\Auth\AccountController` and reuse the web's `CompleteSignupRequest` and `SignupService`. Withdrawal is `WithdrawalService` (web `DELETE /settings/account` with a confirm checkbox, app `DELETE /api/v1/auth/me`): it anonymizes the user, records 30-day rejoin hashes, deletes social accounts/roles/tokens/DB sessions, and queues the encrypted `RevokeAppleToken` job after commit. Nicknames starting with `withdrawn_` are reserved. Account linking is `SocialAccountLinkService` (app `POST/DELETE /api/v1/auth/social-accounts/{provider}`, web settings > 로그인 연결). The web reuses `/auth/{provider}/callback` and tells login and linking apart with the `social_link` session key; `SocialAuth\WebSocialiteDrivers` holds the Apple driver modes (login: stateless + nonce cookie, form_post; linking: stateful, no scope, `response_mode=query`).
- API base (2026-11-20): `routes/api.php` serves `/api/v1` (route names `api.v1.*`), Sanctum Bearer tokens (60 days, stateful/session auth off). Build responses with `App\Http\Responses\ApiResponse` and signal business errors by throwing `App\Exceptions\ApiException` with an `App\Enums\ApiErrorCode`. `ApiExceptionRenderer` turns every `/api/*` exception into the error format. List requests extend `App\Http\Requests\Api\V1\PaginatedRequest`. Scramble serves OpenAPI at `/docs/api` (local only). Rules: `docs/04-development/coding-conventions.md` §1.
- Admin (ADR-003 §6): Filament 5 panel at `/admin` (`Providers\Filament\AdminPanelProvider`) has no login page of its own. Guests are sent to the web `/login`, and `User::canAccessPanel()` allows active users with a `Role::PANEL_ROLES` role (moderator/admin/super_admin). Moderators can enter the panel, but resources marked "admin 이상" in the screen design (ADM-02/03/06) use the `Filament\Concerns\RequiresAdminRole` trait (`Role::ADMIN_ROLES`). `Filament/Resources/Users` is read-only member management (list/search/filters/detail). `Filament/Resources/Games` manages games (create/edit, status instead of delete) with release-date and image tabs (relation managers); `Genres`/`Platforms` share `Filament\Support\GameTaxonomy` (drag to reorder, delete only when unused). Game slugs allow lowercase letters, digits and Hangul (`Game::SLUG_PATTERN`, filled from the title on create); genre/platform slugs are ASCII. Images upload to `games/{id}/` on the default disk, and `GameImage` deletes the old file when a row is deleted or replaced. Korean validation messages for rules without custom messages are in `lang/ko/validation.php`. In Livewire tests, uploaded fake files report the MIME type they were created with, while real requests detect it from the file contents. Only super_admin changes panel roles there (`UserRoleService`); the first admin is set with `php artisan user:role {id|email} super_admin`. Filament assets (`public/js/filament`, `public/css/filament`) are gitignored and published by `filament:upgrade` on composer install. Filament tests use `Livewire::test(...)` with `Filament::setCurrentPanel('admin')`.
- Games (ERD §4): `Game`, `Genre`, `Platform`, `GameImage`, `GameReleaseDate` models with enums `GameStatus`, `GameImageType`, `ReleaseDatePrecision`, `ReleaseStatus`. Extra DB constraints (one cover per game, tba ⇔ NULL date, released needs a date) are listed in the ERD §4 implementation note. Month/quarter/year release dates are stored as the first day of the period, and `displayDate()` formats them. Default genres/platforms come from a migration, so factories use `test-*` slugs. `GameSeeder` makes 30 local sample games. Constraint tests wrap violating statements in `DB::transaction()` (a savepoint), because PostgreSQL aborts the whole transaction on an error. `GameService` holds game search/filter/sort and published-only detail lookup for both the app API (`Api\V1\GameController`: `/games`, `/games/{id}`, `/genres`, `/platforms`, query rules in `GameIndexRequest`) and the web pages (`GameController`: `/games`, `/games/{slug}` with Hangul slugs, Inertia pages `games/Index`, `games/Show` in `PublicLayout`). The web list uses `GameBrowseRequest`, which reuses the API rules but silently drops invalid query values (a 1-char keyword shows a hint instead of a 422). Web props reuse the API resources through `toResponse()->getData(true)['data']`, because `resolve()` leaves nested resource collections that Inertia wraps in `{data: [...]}`. Page prop types are in `resources/js/types/game.ts`. `ReleaseService` builds the release schedule (`Api\V1\ReleaseController` for `/releases`, `/releases/upcoming`, `/games/{id}/releases`; web `ReleaseController` for `/releases?month=YYYY-MM`). It groups rows of the same game/date/precision/status into one `App\Data\ReleaseScheduleEntry`, treats month/quarter/year dates as periods, sorts by period end, and uses KST dates (`ReleaseService::today()`). `FavoriteService` owns `favorite_games` and the `games.favorite_count` cache (insertOrIgnore/delete row counts in one transaction; idempotent add/remove/release-alert; `WithdrawalService` calls `removeAllFor()`). Game detail takes viewer state via `GameDetailResource::forViewer()` (the public API reads an optional token with `$request->user('sanctum')`). Web buttons use `useFavoriteActions()`. Guests go to `/login?redirect=<path>`, and `LoginController::show` stores only same-site paths as the intended URL.
- Points (point policy, ERD §7): every point change goes through `PointService` (`earn` for policy/explicit-amount types, `spend`, `revoke`, `expireOnWithdraw`). It locks the user row, checks the `idempotency_key` (policy §5 formats, e.g. `signup:{user_id}`; a repeat returns `alreadyProcessed`), applies `point_policies` amounts and daily limits by KST service date (`App\Support\ServiceDate`), writes a `point_transactions` row and updates `users.point_balance`/`lifetime_points`. Results are `App\Data\PointResult`, whose `toArray()` is the API's `data.point`. Ledger rows cannot be updated or deleted (model throws; DB CHECKs on amount/balance). Default policy rows come from the migration. `SignupService::complete()` returns `SignupResult` with the signup point. Tests assert balance = ledger sum. `Sanctum::actingAs()` keeps the given model instance, so refresh it after changing points in a test.
- Attendance: `AttendanceService::check()` locks the user row, inserts the `attendances` row (UNIQUE `user_id+attendance_date`, KST date from `ServiceDate`) and grants points in the same transaction; 7th and 30th streak days add bonuses, and the streak restarts at 1 after 30. `AttendanceConcurrencyTest` forks processes (needs `pcntl`) and does not use `RefreshDatabase`, so it cleans up its own rows.
- Reviews: `ReviewService` (create/update/delete, `assertCanWrite()` order per api-spec §7.3, partial UNIQUE `(user_id, game_id) WHERE status <> 'deleted'`), `ReviewLikeService`, `ReviewCommentService`. Score changes call `GameRatingService::recalculateAfterCommit()`, which recomputes `games.review_count`/`rating_avg` and `game_rating_stats` from published reviews under a game row lock; call `recalculate()` after changing review status anywhere else (admin hide etc.). `ReviewWriteRequest` checks write eligibility in `authorize()` so business errors come before validation errors. Blinded/hidden reviews cannot be edited or deleted by the author.
- Community (web only in phase 1, MOB-008): `PostService` (boards, list/search, create/update with image attachments on the public disk, views once per session) and `PostInteractionService` (likes without points, comments). Posts reuse `ReviewStatus`; comments reuse `CommentStatus`. Comment point keys are `comment_create:review:{id}` and `comment_create:post:{id}`. File uploads with PUT go as POST + `_method=put` (`forceFormData`).
- My page (W-17): `MyPageService` (header, dashboard, my reviews/posts incl. blinded/hidden) is shared by the web `MyPageController` (tabs `/mypage/*`, each page gets a `header` prop rendered by `layouts/MyPageLayout.vue` inside `PublicLayout`) and `Api\V1\MyPageController`. `/dashboard` and `GET /attendance` redirect into it; Fortify `home` is `/mypage`.
- Rankings (`docs/01-planning/policies/ranking-policy.md`): `RankingService` writes `ranking_snapshots` per period (delete + insert in one transaction, `RANK()`), run hourly by `rankings:snapshot` from `routes/console.php`. `composer dev` does not run the scheduler, so run the command by hand (or `php artisan schedule:work`) to see rankings locally.
- SSR is skipped while the Vite dev server runs (`public/hot` exists, e.g. during `composer dev`). To check SSR then, POST the page JSON from the HTML to the SSR server's `/render` instead of stopping the user's dev server.
- Levels (`docs/01-planning/policies/level-policy.md`): `user_levels` ordered by `min_points` (base level 0P must exist; it cannot be deleted or re-based). `PointService` calls `UserLevelService::sync()` in the same transaction when lifetime points change. Admin changes queue `RecalculateUserLevels` (`recalculateAll()`, set-based SQL, skips withdrawn), and deletion moves members to the next lower level (`UserLevelService::delete()`). New users get the base level in `User::creating`. `summary()` builds the `level`/`next_level` fields of `/auth/me` and `/points/balance`. Admin group "포인트·등급": `UserLevelResource` and `PointPolicyResource` (edit only, no create/delete). `Game::primaryRelease()` picks the release shown on list cards.
- Feature tests that render an Inertia page need a current Vite manifest. After adding a page, run `npm run build` (or `build:ssr`), or the test fails with "Unable to locate file in Vite manifest".
- CI: `.github/workflows/tests.yml` (repo root) runs on pushes/PRs that touch `backend/**`. It starts a PostgreSQL 18 service on port 5433 with the test DB, runs `npm run build`, then `composer ci:check` (JS lint, TS types, Pint, PHPStan, PHPUnit). Run `composer ci:check` locally (after `npm run build`) to reproduce it.

Production install, cron (`schedule:run`), queue worker, SSR process and deploy steps: `docs/06-operations/server-setup.md`. Update it when adding a scheduled task, queue job type, process or env key.

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
