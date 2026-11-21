<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 랭킹 집계 결과 (ERD §8, 랭킹 정책). 기간마다 점수가 0보다 큰 회원 전체를 순위와 함께 저장한다.
 * snapshot_date는 기간 시작일(주간은 월요일, 월간은 1일, KST)이다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('ranking_type', 30);
            $table->date('snapshot_date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('score');
            $table->integer('rank');
            $table->timestampTz('created_at');

            $table->unique(['ranking_type', 'snapshot_date', 'user_id']);
            $table->index(['ranking_type', 'snapshot_date', 'rank']);
        });

        DB::statement("ALTER TABLE ranking_snapshots ADD CONSTRAINT ranking_snapshots_type_check CHECK (ranking_type IN ('points_weekly', 'reviews_monthly'))");
        DB::statement('ALTER TABLE ranking_snapshots ADD CONSTRAINT ranking_snapshots_values_check CHECK (score > 0 AND rank >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_snapshots');
    }
};
