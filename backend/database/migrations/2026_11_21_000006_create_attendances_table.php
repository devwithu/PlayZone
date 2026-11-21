<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 출석 (ERD §7 attendances, 포인트 정책 §3·§5).
 * 하루 한 번은 UNIQUE (user_id, attendance_date)가 최종적으로 막는다. 지급 포인트는 원장에만 기록한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date')->comment('KST 서비스 일자');
            $table->smallInteger('streak_days')->comment('연속 출석 일수. 30일 다음은 1');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'attendance_date']);
        });

        DB::statement('ALTER TABLE attendances ADD CONSTRAINT attendances_streak_days_check CHECK (streak_days BETWEEN 1 AND 30)');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
