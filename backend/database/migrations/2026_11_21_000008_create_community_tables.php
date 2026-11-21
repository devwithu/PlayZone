<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 커뮤니티: 게시판·게시글·댓글·추천·첨부 이미지 (ERD §6, REQ-401~408).
 * 신고(post_reports)는 신고 검토 작업에서 리뷰 신고와 함께 만든다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('description', 200)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        $boards = [
            'free' => ['자유게시판', '자유롭게 이야기를 나누는 곳'],
            'discussion' => ['게임토론', '게임에 대한 생각과 의견'],
            'guides' => ['공략', '공략과 팁 공유'],
            'qna' => ['질문답변', '궁금한 것을 묻고 답하기'],
            'info' => ['정보공유', '게임 소식과 할인 정보'],
        ];
        $now = now();
        $order = 0;
        foreach ($boards as $slug => [$name, $description]) {
            DB::table('boards')->insert([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'sort_order' => $order += 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('title', 100);
            $table->text('content');
            $table->string('status', 20)->default('published');
            $table->string('hidden_reason', 500)->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->timestampTz('edited_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });

        DB::statement("ALTER TABLE posts ADD CONSTRAINT posts_status_check CHECK (status IN ('published', 'blinded', 'hidden', 'deleted'))");
        DB::statement('ALTER TABLE posts ADD CONSTRAINT posts_counts_check CHECK (view_count >= 0 AND like_count >= 0 AND comment_count >= 0)');
        DB::statement('CREATE INDEX posts_board_latest_index ON posts (board_id, status, created_at DESC)');
        DB::statement('CREATE INDEX posts_board_likes_index ON posts (board_id, status, like_count DESC, id DESC)');
        // 제목·본문 검색 (REQ-402). pg_trgm은 games 마이그레이션에서 켰다
        DB::statement('CREATE INDEX posts_title_trgm_index ON posts USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX posts_content_trgm_index ON posts USING gin (content gin_trgm_ops)');

        Schema::create('post_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('file_path', 255)->comment('public 디스크 기준 상대 경로');
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->integer('size_bytes');
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['post_id', 'sort_order']);
        });

        Schema::create('post_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'post_id']);
            $table->index('post_id');
        });

        Schema::create('post_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('content', 500);
            $table->string('status', 20)->default('published');
            $table->timestampsTz();

            $table->index(['post_id', 'created_at']);
        });

        DB::statement("ALTER TABLE post_comments ADD CONSTRAINT post_comments_status_check CHECK (status IN ('published', 'hidden', 'deleted'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_likes');
        Schema::dropIfExists('post_attachments');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('boards');
    }
};
