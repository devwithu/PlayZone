# 운영 서버 설치·초기화·배포

> 작성일: 2026-11-21
> 대상: 운영(또는 스테이징) 서버에 게임평가단 백엔드(`backend/`)를 처음 설치하고, 이후 배포하는 사람
> 개발 PC 설정은 [개발 환경 구성](../04-development/environment-setup.md)을 본다.

호스팅 업체, 도메인, HTTPS 인증서 발급 방식은 아직 정하지 않았다. 이 문서는 **Ubuntu 서버 한 대**에 모든 것을 올리는 기준으로 쓴다. 예시의 도메인 `example.com`, 경로 `/var/www/playzone`, 계정 `deploy`는 실제 값으로 바꾼다.

## 1. 구성

한 대의 서버에서 아래 프로세스가 함께 돈다. 웹사이트, 앱 API(`/api/v1`), 관리자(`/admin`)는 모두 같은 Laravel 앱이다.

| 프로세스 | 하는 일 | 실행 방법 | 멈추면 |
|---|---|---|---|
| Nginx + PHP-FPM | 웹·API·관리자 요청 처리 | systemd | 사이트 전체 중단 |
| 큐 워커 (`queue:work`) | 회원 등급 재계산, Apple 토큰 폐기 등 백그라운드 작업 | Supervisor | 작업이 Redis에 쌓이기만 하고 처리되지 않음 |
| 스케줄러 (`schedule:run`) | 랭킹 매시 집계 등 정기 작업 | **cron (매분)** | 랭킹이 갱신되지 않음 |
| SSR 서버 (`inertia:start-ssr`, Node) | 공개 페이지를 서버에서 HTML로 렌더링 (검색 노출) | Supervisor | 화면은 뜨지만 검색엔진이 빈 HTML을 받음 |
| PostgreSQL 18 | 데이터베이스 | systemd (또는 관리형 DB) | 사이트 전체 중단 |
| Redis 8 | 캐시, 큐, 스케줄러 잠금 | systemd (또는 관리형 Redis) | 사이트 전체 중단 |

나중에 추가될 것: Laravel Reverb(웹 실시간 알림, P2), Firebase FCM 발송(앱 푸시), S3·R2 파일 저장소.

## 2. 서버 요구사항

| 항목 | 버전·값 |
|---|---|
| OS | Ubuntu 24.04 LTS 이상 (26.04 권장. 24.04는 PHP 8.5용 `ppa:ondrej/php` 필요) |
| PHP | 8.5, `php8.5-fpm` 포함 |
| PHP 확장 | `pdo_pgsql`, `intl`, `mbstring`, `gd`, `zip`, `bcmath`, `curl`, `xml` (Redis는 `predis`라 확장 불필요) |
| Composer | 2.x |
| Node.js | 26 (`backend/.node-version`). 빌드와 **SSR 실행**에 모두 필요 |
| PostgreSQL | 18, UTF-8 |
| Redis | 8 |
| 기타 | Nginx, Supervisor, Git, cron |

메모리는 2GB 이상을 권장한다(PHP-FPM, Node SSR, PostgreSQL, Redis가 함께 돈다).

## 3. 최초 설치

### 3.1 패키지 설치

```bash
sudo apt update
sudo apt install -y nginx supervisor git unzip \
  php8.5-fpm php8.5-cli php8.5-pgsql php8.5-intl php8.5-mbstring \
  php8.5-gd php8.5-zip php8.5-bcmath php8.5-curl php8.5-xml

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 26 (NodeSource 또는 fnm). 시스템 전체에서 node 명령을 쓸 수 있어야 SSR이 Supervisor로 뜬다
```

PostgreSQL 18과 Redis 8은 공식 저장소(apt.postgresql.org, packages.redis.io)로 설치하거나 관리형 서비스를 쓴다. 개발용 `compose.yaml`은 로컬 전용이므로 운영에 그대로 쓰지 않는다.

### 3.2 PHP 업로드 한도

게시글 이미지는 **1개 10MB, 글당 10개**다(REQ-408). 한 요청이 최대 100MB를 조금 넘을 수 있으므로 PHP와 Nginx 한도를 함께 올린다.

`/etc/php/8.5/fpm/conf.d/99-playzone.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 110M
max_file_uploads = 20
memory_limit = 256M
expose_php = Off
```

```bash
sudo systemctl restart php8.5-fpm
```

### 3.3 데이터베이스와 Redis

```bash
sudo -u postgres psql <<'SQL'
CREATE USER game_user WITH PASSWORD '강력한-비밀번호';
CREATE DATABASE game_evaluation OWNER game_user ENCODING 'UTF8' TEMPLATE template0;
SQL
```

- 검색에 쓰는 `pg_trgm` 확장은 첫 마이그레이션이 만든다. `pg_trgm`은 DB 소유자가 만들 수 있는 확장이므로 `game_user`를 DB 소유자로 만들면 된다.
- 테스트 DB(`game_evaluation_test`)는 운영 서버에 만들지 않는다.
- Redis에는 비밀번호(`requirepass`)를 걸고 외부에서 접속할 수 없게 `bind 127.0.0.1`로 둔다.
- DB 시간대는 UTC 그대로 둔다. 앱이 UTC로 저장하고 화면에서 한국 시간으로 바꾼다.

### 3.4 코드 받기

```bash
sudo mkdir -p /var/www/playzone && sudo chown deploy:www-data /var/www/playzone
git clone <저장소 주소> /var/www/playzone
cd /var/www/playzone/backend
```

### 3.5 `.env` 작성

`cp .env.example .env` 후 아래 값을 바꾼다. `.env`는 Git에 올리지 않는다.

| 키 | 운영 값 | 비고 |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | **반드시** false. true면 오류 화면에 비밀값이 보인다 |
| `APP_URL` | `https://example.com` | 소셜 로그인 콜백 주소가 이 값으로 만들어진다 |
| `APP_KEY` | `php artisan key:generate`로 생성 | 한 번 정하면 바꾸지 않는다(세션·암호화 데이터가 깨짐) |
| `LOG_STACK` | `daily` | 날짜별 로그 파일 |
| `LOG_LEVEL` | `warning` | |
| `DB_HOST`, `DB_PORT` | `127.0.0.1`, `5432` | 개발용 5433이 아니다 |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | 3.3에서 만든 값 | |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | `127.0.0.1`, `6379`, 설정한 비밀번호 | 개발용 6380이 아니다 |
| `CACHE_STORE`, `QUEUE_CONNECTION` | `redis`, `redis` | 그대로 |
| `SESSION_DRIVER` | `database` | 그대로 |
| `SESSION_SECURE_COOKIE` | `true` | HTTPS에서만 쿠키 전송 (`.env.example`에 없으므로 추가) |
| `FILESYSTEM_DISK` | `public` | S3·R2로 옮길 때 이 값과 `AWS_*`만 바꾼다 |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Google Cloud 콘솔 값 | 승인된 리디렉션 URI에 `https://example.com/auth/google/callback` 등록 |
| `APPLE_CLIENT_ID`, `APPLE_CLIENT_SECRET` | Apple Developer 값 | Return URL에 `https://example.com/auth/apple/callback` 등록 |
| `GOOGLE_ID_TOKEN_AUDIENCES`, `APPLE_ID_TOKEN_AUDIENCES` | 앱 클라이언트 ID, iOS 번들 ID | 앱 로그인용 (ADR-003) |
| `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY` | Apple 키 정보, `.p8` 파일 경로 | 3.6 참고 |
| `MAIL_*` | 아직 쓰지 않음 | 메일 발송 기능이 생기면 정한다 |

### 3.6 Apple 개인 키 파일

Apple 로그인 토큰 폐기(탈퇴, 연결 해제)에 쓰는 `.p8` 파일을 서버에만 둔다.

```bash
mkdir -p storage/app/private/apple
# 로컬에서 scp 등으로 AuthKey_XXXX.p8 업로드
chmod 640 storage/app/private/apple/AuthKey_XXXX.p8
# .env: APPLE_PRIVATE_KEY=storage/app/private/apple/AuthKey_XXXX.p8
```

### 3.7 설치와 초기화

```bash
cd /var/www/playzone/backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate          # 최초 1회만
npm ci
npm run build:ssr                 # 브라우저용 public/build + SSR용 bootstrap/ssr
php artisan migrate --force       # 테이블과 기본 데이터 생성 (아래 4장)
php artisan storage:link          # public/storage → storage/app/public
php artisan optimize              # 설정·라우트·뷰·이벤트 캐시
php artisan filament:optimize     # 관리자 화면 컴포넌트·아이콘 캐시

# 웹 서버가 쓸 수 있어야 하는 폴더
sudo chown -R deploy:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

**`php artisan db:seed`는 운영에서 실행하지 않는다.** `DatabaseSeeder`는 테스트 회원과 가짜 게임을 만드는 개발용이다. 운영에 필요한 기본 데이터는 마이그레이션이 넣는다.

### 3.8 Nginx

`/etc/nginx/sites-available/playzone`:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/playzone/backend/public;
    index index.php;

    # 게시글 이미지 업로드 (10MB × 10개)
    client_max_body_size 110M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # 빌드 파일은 이름에 해시가 있어 오래 캐시해도 된다
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/playzone /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

HTTPS는 Let's Encrypt(`certbot --nginx`) 등으로 붙인다. Apple 로그인은 HTTPS가 아니면 동작하지 않는다.

### 3.9 큐 워커와 SSR 서버 (Supervisor)

`/etc/supervisor/conf.d/playzone.conf`:

```ini
[program:playzone-queue]
command=php /var/www/playzone/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
user=deploy
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/playzone/backend/storage/logs/queue.log

[program:playzone-ssr]
command=php /var/www/playzone/backend/artisan inertia:start-ssr
user=deploy
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/playzone/backend/storage/logs/ssr.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status        # 두 프로그램 모두 RUNNING인지 확인
php artisan inertia:check-ssr    # SSR 서버 응답 확인
```

- SSR 서버는 `127.0.0.1:13714`에서 돈다(`config/inertia.php`). 외부에 열지 않는다.
- `--max-time=3600`은 워커가 한 시간마다 스스로 재시작해 메모리가 쌓이지 않게 한다. Supervisor가 다시 띄운다.

### 3.10 스케줄러 cron

Laravel 스케줄러는 cron이 **매분** `schedule:run`을 불러야 돈다. 스케줄 자체(무엇을 언제 돌릴지)는 코드(`backend/routes/console.php`)에 있다.

```bash
sudo crontab -u deploy -e
```

```cron
* * * * * cd /var/www/playzone/backend && php artisan schedule:run >> /dev/null 2>&1
```

- 웹 서버 계정이 아니라 **코드를 배포하는 계정(deploy)**으로 등록한다. root로 돌리면 root 소유 캐시·로그 파일이 생겨 웹 서버가 쓰지 못한다.
- 등록 확인: `php artisan schedule:list`에 작업과 다음 실행 시각이 보여야 한다.
- 수동 실행: `php artisan schedule:run` 또는 개별 명령(아래 표).

현재 스케줄:

| 명령 | 주기 | 내용 | 문서 |
|---|---|---|---|
| `rankings:snapshot` | 매시 정각 | 이번 주·이번 달 랭킹 집계. 새 기간 첫날에는 지난 기간도 다시 집계 | [랭킹 정책](../01-planning/policies/ranking-policy.md) §3 |

스케줄 작업은 `withoutOverlapping()`, `onOneServer()`를 쓴다. 잠금은 Redis 캐시에 두므로 서버를 여러 대로 늘려도 한 대에서만 돈다.

### 3.11 첫 관리자 지정

관리자도 소셜 로그인으로만 들어온다(ADR-003).

1. 사이트에서 Google 또는 Apple로 로그인하고 가입 완료(약관, 닉네임)까지 마친다.
2. 서버에서 역할을 준다.
   ```bash
   php artisan user:role {회원 ID 또는 이메일} super_admin
   ```
3. `https://example.com/admin`에 들어가 본다.

### 3.12 설치 확인 체크리스트

- [ ] `https://example.com` 메인, `/games`, `/community`, `/rankings`가 열린다.
- [ ] 페이지 소스 보기(`view-source:`)에 게임 제목 등 본문 HTML이 들어 있다(SSR 동작).
- [ ] Google·Apple 로그인과 가입 완료가 된다.
- [ ] 출석 체크가 되고 포인트가 오른다.
- [ ] 게시글에 이미지를 올리면 보인다(`storage:link`, 업로드 한도).
- [ ] `sudo supervisorctl status`에서 queue, ssr이 RUNNING이다.
- [ ] `php artisan schedule:list`에 `rankings:snapshot`이 있고, 정각이 지난 뒤 `/rankings`에 집계 시각이 보인다.
- [ ] `/admin`에 관리자로 들어가진다.
- [ ] `APP_DEBUG=false`다(없는 주소에서 오류 상세가 보이지 않는다).
- [ ] API 문서(`/docs/api`)는 운영에서 보이지 않는다(Scramble 기본값: local 환경에서만 공개).

## 4. 마이그레이션이 넣는 기본 데이터

운영 초기화는 `php artisan migrate --force` 하나로 끝난다. 아래 기본 데이터는 마이그레이션이 함께 넣으며, 이후에는 관리자 화면에서 바꾼다.

| 데이터 | 마이그레이션 | 관리 화면 |
|---|---|---|
| 역할 (`moderator`, `admin`, `super_admin`) | `2026_11_19_000001_create_roles_tables` | 명령 `user:role` |
| 기본 장르·플랫폼 | `2026_11_21_000002_insert_default_genres_and_platforms` | 관리자 > 게임 정보 |
| 포인트 정책 (사유별 금액·일일 한도) | `2026_11_21_000004_create_point_tables` | 관리자 > 포인트 정책 |
| 회원 등급 | `2026_11_21_000005_create_user_levels_tables` | 관리자 > 회원 등급 |
| 리뷰 세부 평가 항목 | `2026_11_21_000007_create_review_tables` | — |
| 기본 게시판 5개 | `2026_11_21_000008_create_community_tables` | 관리자 > 게시판 |

게임 정보는 관리자 화면에서 직접 등록한다.

## 5. 배포 (업데이트)

`main`에 올린 코드를 서버에 반영하는 순서다.

```bash
cd /var/www/playzone/backend

php artisan down --render="errors::503" --retry=60   # 점검 모드 (DB 구조가 바뀔 때 권장)

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build:ssr
php artisan migrate --force
php artisan optimize              # 캐시 다시 만들기 (예전 캐시를 지우고 새로 만든다)
php artisan filament:optimize

php artisan queue:restart         # 워커가 지금 작업을 마치고 새 코드로 다시 뜬다
php artisan inertia:stop-ssr      # SSR 서버를 멈추면 Supervisor가 새 빌드로 다시 띄운다

php artisan up
```

- 마이그레이션이 없는 작은 변경은 `down`/`up` 없이 해도 된다.
- `optimize`로 설정을 캐시하면 `.env`를 바꾼 뒤에도 **`php artisan optimize`를 다시 실행해야** 반영된다.
- `npm run build:ssr`가 실패하면 그 상태로 `up` 하지 말고 원인을 고친다. 빌드 파일이 없으면 모든 화면이 오류를 낸다.
- 스케줄은 코드에 있으므로 배포만 하면 바뀐다. cron은 다시 만지지 않는다.

## 6. 초기화와 재집계

### 6.1 운영 데이터 초기화 (하지 않는다)

`php artisan migrate:fresh`, `migrate:reset`, `db:wipe`는 **모든 데이터를 지운다.** 운영 서버에서는 실행하지 않는다(`APP_ENV=production`이면 확인을 묻지만, `--force`를 붙이면 묻지 않는다).

스테이징 서버를 처음 상태로 돌릴 때만 쓴다.

```bash
php artisan migrate:fresh --force   # 스테이징 전용. 기본 데이터까지 다시 들어간다
php artisan storage:link
```

업로드 파일(`storage/app/public/posts` 등)은 DB와 따로이므로 필요하면 함께 지운다.

### 6.2 다시 계산하기

| 상황 | 명령 |
|---|---|
| 랭킹이 비었거나 틀렸다 (cron이 멈췄던 경우 등) | `php artisan rankings:snapshot` (이번 기간) |
| 특정 기간 랭킹 다시 집계 | `php artisan rankings:snapshot --type=points_weekly --date=2026-11-18` / `--type=reviews_monthly --date=2026-11-01` |
| 등급 기준을 바꾼 뒤 전체 회원 등급 재계산 | 관리자 > 회원 등급에서 저장하면 큐 작업으로 돈다 (큐 워커 필요) |
| 캐시가 이상하다 | `php artisan optimize:clear` 후 `php artisan optimize` |

## 7. 로그와 점검

| 무엇 | 어디 |
|---|---|
| 앱 오류 | `backend/storage/logs/laravel-YYYY-MM-DD.log` |
| 큐 워커 | `backend/storage/logs/queue.log`, 실패한 작업은 `php artisan queue:failed` |
| SSR | `backend/storage/logs/ssr.log`, 상태는 `php artisan inertia:check-ssr` |
| Nginx | `/var/log/nginx/error.log` |
| 스케줄 | `php artisan schedule:list` (다음 실행 시각) |

자주 생기는 문제:

| 증상 | 확인할 것 |
|---|---|
| 랭킹 집계 시각이 몇 시간째 그대로 | cron 등록(`crontab -u deploy -l`), `schedule:list`, 로그 |
| 페이지 소스에 본문 HTML이 없다 | SSR 프로세스(`supervisorctl status`), `inertia:check-ssr`, `bootstrap/ssr` 빌드 여부 |
| 등급이 바뀌지 않는다, 탈퇴 후 Apple 연결이 남는다 | 큐 워커 상태, `queue:failed` |
| 이미지 업로드 시 413 오류 | Nginx `client_max_body_size`, PHP `post_max_size` |
| 이미지 주소가 404 | `php artisan storage:link` |
| `.env`를 바꿨는데 그대로 | `php artisan optimize` 다시 실행 |
| 로그인 후 콜백 오류 | `APP_URL`, 소셜 콘솔의 리디렉션 주소, HTTPS |

백업, 모니터링, 장애 대응은 아직 정하지 않았다. 정하면 [백업·복구](backup-recovery.md), [모니터링](monitoring.md), [장애 대응](incident-response.md)에 쓴다.

## 8. 아직 정하지 않은 것

- 호스팅 업체, 서버 사양, 도메인
- HTTPS 인증서 발급·갱신 방식
- DB 백업 주기와 보관 위치
- 파일 저장소를 S3 또는 Cloudflare R2로 옮기는 시점 (`FILESYSTEM_DISK`와 `AWS_*`만 바꾸면 되도록 DB에는 상대 경로만 저장한다)
- Reverb(웹 실시간 알림)와 FCM(앱 푸시)을 붙일 때 추가할 프로세스·환경 변수
- 무중단 배포 방식 (지금은 점검 모드로 배포)
