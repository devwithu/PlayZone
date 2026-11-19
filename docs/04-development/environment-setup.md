# 개발 환경 구성

> 작성일: 2026-11-19
> 대상: 집(macOS)과 회사(Windows)를 오가며 같은 저장소로 개발

## 1. 구성 원칙

| 항목 | 방식 | 이유 |
|---|---|---|
| PostgreSQL, Redis | **Docker** (`compose.yaml`) | 두 PC에서 같은 버전·설정. 로컬 설치 불필요 |
| PHP, Composer, Node.js | 각 PC에 직접 설치 (Windows는 **WSL2** 안에) | 실행 속도, IDE 연동 |
| 줄바꿈 | 저장소는 **LF로 통일** (`.gitattributes`, `.editorconfig`) | Windows CRLF로 인한 diff·스크립트 오류 방지 |
| 스크립트 | bash 전용 스크립트 대신 `composer`·`npm` 스크립트 사용 | 두 OS에서 같은 명령 |
| 비밀값 | `backend/.env`는 PC마다 따로, Git에 올리지 않음 | `.env.example`만 공유 |

## 2. 필요한 버전

| 도구 | 버전 | 확인 명령 |
|---|---|---|
| PHP | 8.5 | `php -v` |
| PHP 확장 | `pdo_pgsql`, `intl`, `mbstring`, `gd`, `zip`, `bcmath`, `curl`, `xml` | `php -m` |
| Composer | 2.x | `composer -V` |
| Node.js | 26 (LTS, `.node-version`) | `node -v` |
| Docker | Docker Desktop 최신 | `docker compose version` |
| PostgreSQL | 18 (Docker) | — |
| Redis | 8 (Docker) | — |

## 3. macOS (집)

```bash
# PHP, Composer, Node가 없을 때 (Homebrew)
brew install php composer node

# 저장소 루트에서 DB·Redis 실행
docker compose up -d
docker compose ps        # STATUS가 healthy인지 확인
```

2026-11-19 기준 맥북: PHP 8.5.12, Composer 2.11.1, Node 26.13.0, Docker 29.9.2 확인 완료.

## 4. Windows (회사)

Windows에서는 **WSL2(Ubuntu) 안에서 개발**한다. Laravel 공식 도구와 운영 서버(Linux)와 환경이 같고, 파일 감시(Vite)와 Composer가 훨씬 빠르다.

### 4.1 최초 1회 설정

1. **WSL2 설치** (PowerShell 관리자): `wsl --install -d Ubuntu`
2. **Docker Desktop 설치** → Settings → Resources → WSL Integration에서 Ubuntu 켜기
3. **Ubuntu 안에서 PHP·Composer·Node 설치**
   ```bash
   sudo add-apt-repository ppa:ondrej/php -y && sudo apt update
   sudo apt install -y php8.5-cli php8.5-pgsql php8.5-intl php8.5-mbstring \
     php8.5-gd php8.5-zip php8.5-bcmath php8.5-curl php8.5-xml unzip
   # Composer: https://getcomposer.org/download/ 안내대로 설치
   # Node: fnm 또는 nvm으로 설치하면 .node-version(26)을 따른다
   ```
4. **Git 설정** (Ubuntu 안에서)
   ```bash
   git config --global core.autocrlf input
   git config --global user.name "..."
   git config --global user.email "..."
   ```
5. **저장소를 WSL 파일 시스템에 clone한다.**
   ```bash
   mkdir -p ~/code && cd ~/code
   git clone <저장소 주소> PlayZone
   ```
   `C:\` 아래(`/mnt/c/...`)에 두면 파일 접근이 매우 느리다. 반드시 `~/code` 같은 WSL 경로에 둔다.
6. **에디터:** VS Code의 WSL 확장(또는 PhpStorm의 WSL 원격)으로 `~/code/PlayZone`을 연다.
7. **Laravel 앱 첫 설정** (Ubuntu 안에서)
   ```bash
   cd ~/code/PlayZone && docker compose up -d
   cd backend
   cp .env.example .env && composer install && php artisan key:generate
   php artisan migrate && npm ci && npm run build
   composer test   # 전부 통과하면 준비 완료
   ```
   PHP 확장은 `pdo_pgsql`이 필요하다(위 `php8.5-pgsql`). Redis는 `predis`(PHP 코드)로 연결하므로 phpredis 확장은 없어도 된다.

### 4.2 매일 실행

```bash
cd ~/code/PlayZone
docker compose up -d
```

> 대안: Laravel Herd for Windows로 PHP를 Windows에 직접 설치할 수도 있다. 다만 맥과 운영 서버가 모두 Unix 계열이므로 WSL2를 기본으로 한다.

## 5. Docker 서비스

| 서비스 | 호스트 포트 | 접속 정보 |
|---|---|---|
| PostgreSQL 18 | **5433** | DB `game_evaluation`, 사용자 `game_user`, 비밀번호 `secret` (로컬 전용) |
| PostgreSQL 테스트 DB | 5433 | DB `game_evaluation_test` (PHPUnit 테스트용) |
| Redis 8 | **6380** | 비밀번호 없음 |

- 기본 포트(5432, 6379)는 다른 프로젝트와 겹치지 않도록 피했다. 바꾸려면 저장소 루트에 `.env`를 만들고 `PLAYZONE_DB_PORT=5434`처럼 지정한다.
- 두 DB 모두 `pg_trgm` 확장이 설치되어 있고, 인코딩은 UTF-8, 시간대는 UTC다.
- 데이터는 Docker named volume(`playzone_postgres-data`, `playzone_redis-data`)에 저장된다. **PC마다 따로** 있으므로 두 PC의 DB 데이터는 공유되지 않는다. 필요한 데이터는 시더(Seeder)로 만든다.

| 명령 | 동작 |
|---|---|
| `docker compose up -d` | 실행 |
| `docker compose stop` | 중지 (데이터 유지) |
| `docker compose down -v` | **삭제 후 초기화** (데이터 삭제, 다음 실행 때 `docker/postgres/init/` 다시 실행) |
| `docker compose exec postgres psql -U game_user -d game_evaluation` | DB 접속 |
| `docker compose exec redis redis-cli` | Redis 접속 |

### Laravel `.env` (`backend/.env.example`에 반영됨)

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=game_evaluation
DB_USERNAME=game_user
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6380
```

## 6. pg_trgm 한국어 검색 확인 결과 (2026-11-19)

- 한글이 정상적으로 트라이그램으로 분해되고, GIN 인덱스와 `ILIKE '%전설%'` 중간 일치 검색이 동작한다.
- 한글 정렬 순서(가 < 나)도 정상이다.
- **오타 검색은 `similarity()` 대신 `word_similarity()`(`<%` 연산자)를 쓴다.** 긴 제목에서는 `similarity`가 0.1 안팎으로 낮게 나온다.
  - `'파이널 판타즈' <% '파이널 판타지 7'` → 0.75, 일치
  - `'젤다 젼설'` → 0.33. 한글은 단어가 짧아 한 글자 오타에도 점수가 크게 떨어진다.
- 따라서 **기본 검색은 `ILIKE` 중간 일치**, 결과가 없을 때 "혹시 이 게임?" 추천에만 `word_similarity`를 쓴다.

## 7. 두 PC를 오갈 때 주의

- **파일명 대소문자:** macOS는 기본적으로 대소문자를 구분하지 않고, WSL·운영 서버(Linux)는 구분한다. `GameService.php`를 `gameService.php`로 바꾸는 식의 이름 변경은 `git mv`로 하고, 클래스명과 파일명을 정확히 맞춘다.
- **작업 전후 동기화:** 시작할 때 `git pull`, 끝낼 때 커밋·푸시. 원격 저장소(GitHub 등) 연결이 필요하다.
- **의존성:** `composer.lock`, `package-lock.json`을 커밋하고, 다른 PC에서는 `composer install`, `npm ci`로 같은 버전을 설치한다.
- **`backend/.env`:** PC마다 따로 만든다(`cp .env.example .env` 후 `php artisan key:generate`). 소셜 로그인 키 같은 비밀값은 Git이 아닌 안전한 곳(비밀번호 관리자 등)에 보관해 옮긴다.
- **다른 PC에서 받은 뒤 매번** (`backend/`에서):
  ```bash
  git pull
  composer install && npm ci          # lock 파일이 바뀌었을 때
  php artisan migrate:fresh --seed    # 마이그레이션이 바뀌었을 때
  npm run build                       # 화면(Inertia 페이지)이 추가됐을 때
  composer test
  ```
  출시 전까지는 처음 만든 마이그레이션 파일을 그 자리에서 고치기도 하므로, `migrate`가 아니라 `migrate:fresh`로 DB를 다시 만든다(로컬 데이터는 지워진다). 출시 후에는 마이그레이션을 고치지 않고 새로 추가한다.
- **화면을 추가하면 빌드부터:** Inertia 페이지를 렌더링하는 테스트는 Vite 빌드 결과(manifest)를 읽는다. 빌드하지 않으면 "Unable to locate file in Vite manifest"로 실패한다.
