<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 리뷰·세부 점수·수정 이력·추천·댓글·세부 평균 (ERD §5, 리뷰 정책).
 * 신고(review_reports)는 관리자 리뷰 관리·신고 검토 작업에서 만든다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('slug', 30)->unique();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        $categories = [
            'graphics' => '그래픽',
            'sound' => '사운드',
            'story' => '스토리',
            'controls' => '조작감',
            'gameplay' => '게임성',
            'value' => '가성비',
        ];
        $now = now();
        $order = 0;
        foreach ($categories as $slug => $name) {
            DB::table('rating_categories')->insert([
                'name' => $name,
                'slug' => $slug,
                'sort_order' => $order += 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('game_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('platform_id')->constrained();
            $table->string('title', 50);
            $table->text('content');
            $table->smallInteger('overall_score');
            $table->boolean('has_spoiler')->default(false);
            $table->string('status', 20)->default('published');
            $table->string('hidden_reason', 500)->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->timestampTz('edited_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });

        DB::statement("ALTER TABLE game_reviews ADD CONSTRAINT game_reviews_status_check CHECK (status IN ('published', 'blinded', 'hidden', 'deleted'))");
        DB::statement('ALTER TABLE game_reviews ADD CONSTRAINT game_reviews_overall_score_check CHECK (overall_score BETWEEN 1 AND 10)');
        DB::statement('ALTER TABLE game_reviews ADD CONSTRAINT game_reviews_counts_check CHECK (like_count >= 0 AND comment_count >= 0)');
        // 게임당 리뷰 1개. 작성자가 지운 리뷰는 빼고 센다 (리뷰 정책 §3)
        DB::statement("CREATE UNIQUE INDEX game_reviews_user_game_unique ON game_reviews (user_id, game_id) WHERE status <> 'deleted'");
        // 목록 정렬 4종 (추천순·최신순·점수순)
        DB::statement('CREATE INDEX game_reviews_game_likes_index ON game_reviews (game_id, status, like_count DESC, id DESC)');
        DB::statement('CREATE INDEX game_reviews_game_latest_index ON game_reviews (game_id, status, created_at DESC)');
        DB::statement('CREATE INDEX game_reviews_game_score_index ON game_reviews (game_id, status, overall_score)');

        Schema::create('review_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('game_reviews')->cascadeOnDelete();
            $table->foreignId('rating_category_id')->constrained();
            $table->smallInteger('score');

            $table->unique(['review_id', 'rating_category_id']);
        });

        DB::statement('ALTER TABLE review_ratings ADD CONSTRAINT review_ratings_score_check CHECK (score BETWEEN 1 AND 10)');

        Schema::create('review_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('game_reviews')->cascadeOnDelete();
            $table->string('title', 50);
            $table->text('content');
            $table->smallInteger('overall_score');
            $table->foreignId('platform_id')->constrained();
            $table->boolean('has_spoiler');
            $table->jsonb('ratings')->comment('세부 점수 {slug: score}');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['review_id', 'created_at']);
        });

        Schema::create('review_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('game_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'review_id']);
            $table->index('review_id');
        });

        Schema::create('review_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('game_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('content', 500);
            $table->string('status', 20)->default('published');
            $table->timestampsTz();

            $table->index(['review_id', 'created_at']);
        });

        DB::statement("ALTER TABLE review_comments ADD CONSTRAINT review_comments_status_check CHECK (status IN ('published', 'hidden', 'deleted'))");

        Schema::create('game_rating_stats', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('avg_score', 3, 1);
            $table->integer('score_count');

            $table->primary(['game_id', 'rating_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rating_stats');
        Schema::dropIfExists('review_comments');
        Schema::dropIfExists('review_likes');
        Schema::dropIfExists('review_revisions');
        Schema::dropIfExists('review_ratings');
        Schema::dropIfExists('game_reviews');
        Schema::dropIfExists('rating_categories');
    }
};
