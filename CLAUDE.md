# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Language

**사용자와의 모든 대화는 한국어로 한다.** (Always respond to the user in Korean.)

## Current state

This repo is a **scaffold with no application code yet**. Every `src/`, `migrations/`, `shared/*` and `scripts/` directory holds only a `.gitkeep`, and most docs under `docs/` are empty placeholders. There are no build, lint or test commands yet. Do not invent any. Add them here once a `composer.json` / `package.json` / Unity project exists.

The only documents with real content (all in Korean):

- `docs/00-overview/game-evaluation-development-design.md`: the authoritative technical design for the product (stack, DB schema, API, business rules, roadmap). Identical copies exist at `docs/game-evaluation-development-design.md` and `untitled folder/game-evaluation-development-design.md`. Treat the `00-overview` copy as canonical, and if you edit it, flag that the copies have diverged.
- `git-project-documentation-structure.md`: the rationale for the repo layout and the docs, branch and commit conventions summarized below.

## Product: 게임평가단 (game evaluation community)

A platform for game info, release schedules, user reviews/ratings, community boards, attendance check-in, points, rankings and events. It has three clients on **one Laravel backend and one PostgreSQL database**:

| Client | Tech | Auth |
|---|---|---|
| Website | Laravel Blade + Vue islands (not Inertia) | Laravel session + CSRF |
| Admin | Filament (`/admin`) | Filament auth + role permissions |
| Mobile app | Unity C# (Android/iOS) | Sanctum Bearer token |

Supporting services: Redis (cache/queue), Laravel Reverb (websocket notifications to the web), Firebase FCM (mobile push), S3-compatible file storage. Target versions in the design doc: PHP 8.3+, Laravel 13.x, Filament ^5.0.

### Repository layout (decided, see `docs/07-meetings/decisions/ADR-001-repo-structure.md`)

- `backend/`: the **single Laravel project**. It contains the website (`resources/views` + `resources/js`), the API (`app/Http/Controllers/Api/V1`) and the Filament admin, following the folder structure in design doc §2. Run all `php artisan`, `composer` and `npm` commands from `backend/`.
- `apps/mobile/`: the Unity project.
- `apps/web/` is **not used**. Do not put website code there.

The project has one developer (solo), so plan work sequentially. See `docs/00-overview/roadmap.md`.

### Architectural rules from the design doc

- **Thin controllers, shared services.** Business logic lives in `app/Services/*Service.php` (Game, Review, Attendance, Point, Notification) and is reused by Web controllers, `Api/V1` controllers and Filament. Validation goes in FormRequests (`app/Http/Requests/Api/V1`) and authorization in Policies.
- **Unity is only an API client.** It never touches the DB and never scrapes HTML. All mobile access goes through versioned REST at `/api/v1/`.
- **Uniform API envelope:** `{ "success": bool, "message": string, "data": {...} }` on success, with paginated lists under `data.items` + `data.pagination` (`current_page`, `last_page`, `per_page`, `total`). Errors are `{ "success": false, "message", "errors": {} }`.
- **Points and attendance integrity:** the server decides dates and point amounts and never trusts client values. Attendance, point grants and review likes must use a DB transaction **plus** a UNIQUE constraint (e.g. `user_id + attendance_date`). All point changes go through `PointService`, which keeps a transaction ledger (`point_transactions`) and the user's balance consistent, using an idempotency/dedup key per source (attendance, review, event).
- **Events after commit:** broadcast events (e.g. `ReviewCreated` → Reverb → Vue, plus FCM push) only after the DB transaction commits.
- **Data conventions:** store timestamps in UTC and convert to KST for display. Review scores are 1–10 with a CHECK constraint. Prefer status changes and soft retention over hard deletes. Unique keys: email, game slug, `user_id+game_id` (favorites), `user_id+review_id` (review likes).
- Vue is mounted per element via `data-vue-component` / `data-props` attributes in Blade (see design doc §3.3). Blade handles page structure and SEO, and Vue handles interactive widgets.

Settle the ERD, API spec, point policy and review policy before implementing features (design doc §12).

## Documentation conventions

- Classify docs by **what kind of document it is**, not by when it was written. For example, an API spec belongs in `docs/03-architecture/api/` even if written during development. Folders are `00-overview` through `08-releases`.
- Write docs in Markdown. API specs may be OpenAPI YAML.
- Requirement IDs: `REQ-###` (common), `WEB-###`, `MOB-###`, `API-###`, `TC-###` (test cases), so they can be traced across docs.
- Meeting notes go in `docs/07-meetings/YYYY/YYYY-MM-DD-topic.md`. Architecture decisions go separately in `docs/07-meetings/decisions/ADR-###-topic.md`.

## Git conventions

- Branches: `main` (stable), `develop` (integration), `feature/*`, `docs/*`, `hotfix/*`.
- Commit prefixes: `feat:`, `fix:`, `docs:`, `design:`, `test:`, `refactor:`. Messages are typically written in Korean, e.g. `docs: 요구사항 정의서 수정`.
- Doc changes are also reviewed through PRs.
