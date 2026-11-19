-- 컨테이너를 처음 만들 때(볼륨이 비어 있을 때) 한 번만 실행된다.
-- 운영 DB에서는 Laravel 마이그레이션이 pg_trgm 확장을 만든다 (ERD §12).

-- 개발 DB
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- 테스트 DB (Pest)
CREATE DATABASE game_evaluation_test OWNER game_user;
\connect game_evaluation_test
CREATE EXTENSION IF NOT EXISTS pg_trgm;
