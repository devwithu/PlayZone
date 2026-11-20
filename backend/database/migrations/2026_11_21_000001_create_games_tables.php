<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD §4 게임 정보: games, genres, platforms, game_genre, game_platform, game_images, game_release_dates.
     * 미션 리뷰 집계 컬럼(ERD §13)은 2차에서 추가한다.
     */
    public function up(): void
    {
        // 로컬·CI는 init 스크립트가 미리 만든다. 운영 DB는 여기서 만든다 (pg_trgm은 DB 소유자가 만들 수 있는 trusted 확장)
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('developer', 100)->nullable();
            $table->string('publisher', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->integer('review_count')->default(0);
            $table->decimal('rating_avg', 3, 1)->nullable();
            $table->integer('favorite_count')->default(0);
            $table->timestampsTz();

            $table->index(['status', 'rating_avg']);
        });

        DB::statement("ALTER TABLE games ADD CONSTRAINT games_status_check CHECK (status IN ('draft', 'published', 'hidden'))");
        DB::statement('ALTER TABLE games ADD CONSTRAINT games_counts_check CHECK (review_count >= 0 AND favorite_count >= 0)');
        DB::statement('ALTER TABLE games ADD CONSTRAINT games_rating_avg_check CHECK (rating_avg IS NULL OR rating_avg BETWEEN 1 AND 10)');
        // 제목 중간 일치(ILIKE)·오타 추천(word_similarity) 검색용 (ERD §12-5)
        DB::statement('CREATE INDEX games_title_trgm_index ON games USING gin (title gin_trgm_ops)');

        foreach (['genres', 'platforms'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name', 50);
                $table->string('slug', 50)->unique();
                $table->smallInteger('sort_order')->default(0);
                $table->timestampsTz();
            });
        }

        Schema::create('game_genre', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('genre_id')->constrained()->restrictOnDelete();
            $table->primary(['game_id', 'genre_id']);
            $table->index(['genre_id', 'game_id']);
        });

        Schema::create('game_platform', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->primary(['game_id', 'platform_id']);
            $table->index(['platform_id', 'game_id']);
        });

        Schema::create('game_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->string('image_path', 255);
            $table->string('image_type', 20);
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['game_id', 'image_type', 'sort_order']);
        });

        DB::statement("ALTER TABLE game_images ADD CONSTRAINT game_images_image_type_check CHECK (image_type IN ('cover', 'screenshot', 'banner'))");
        // 표지는 게임당 하나
        DB::statement("CREATE UNIQUE INDEX game_images_one_cover_unique ON game_images (game_id) WHERE image_type = 'cover'");

        Schema::create('game_release_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('platform_id')->constrained()->restrictOnDelete();
            $table->date('release_date')->nullable();
            $table->string('date_precision', 10);
            $table->string('release_status', 20);
            $table->timestampsTz();

            $table->unique(['game_id', 'platform_id']);
            $table->index('release_date');
            $table->index(['release_status', 'release_date']);
        });

        DB::statement("ALTER TABLE game_release_dates ADD CONSTRAINT game_release_dates_date_precision_check CHECK (date_precision IN ('day', 'month', 'quarter', 'year', 'tba'))");
        DB::statement("ALTER TABLE game_release_dates ADD CONSTRAINT game_release_dates_release_status_check CHECK (release_status IN ('announced', 'scheduled', 'delayed', 'released', 'cancelled'))");
        // 미정(tba)이면 날짜가 없고, 그 외에는 날짜가 있다. 월·분기·연 단위는 그 기간의 첫날로 저장한다
        DB::statement("ALTER TABLE game_release_dates ADD CONSTRAINT game_release_dates_tba_check CHECK ((date_precision = 'tba') = (release_date IS NULL))");
        // 출시 완료는 미정(tba)일 수 없다 (출시 일정 정렬의 기준. 오래된 게임은 연 단위만 알아도 된다)
        DB::statement("ALTER TABLE game_release_dates ADD CONSTRAINT game_release_dates_released_check CHECK (release_status <> 'released' OR release_date IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_release_dates');
        Schema::dropIfExists('game_images');
        Schema::dropIfExists('game_platform');
        Schema::dropIfExists('game_genre');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('genres');
        Schema::dropIfExists('games');
    }
};
