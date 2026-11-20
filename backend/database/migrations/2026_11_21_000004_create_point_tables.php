<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD §7 포인트: point_policies(금액·한도 설정), point_transactions(원장).
     * 모든 포인트 변경은 App\Services\PointService를 거친다 (포인트 정책 §2).
     */
    public function up(): void
    {
        Schema::create('point_policies', function (Blueprint $table) {
            $table->id();
            $table->string('action', 40)->unique();
            $table->integer('points');
            $table->smallInteger('daily_limit_count')->nullable();
            $table->integer('daily_limit_points')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE point_policies ADD CONSTRAINT point_policies_values_check CHECK (points > 0 AND (daily_limit_count IS NULL OR daily_limit_count > 0) AND (daily_limit_points IS NULL OR daily_limit_points > 0))');

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('type', 40);
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 150)->unique();
            $table->string('description', 500)->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users');
            $table->date('service_date');
            // 원장은 수정·삭제하지 않는다 (포인트 정책 §2-5). updated_at 없음
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'type', 'service_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        DB::statement('ALTER TABLE point_transactions ADD CONSTRAINT point_transactions_amount_check CHECK (amount <> 0 AND balance_after >= 0)');

        // 잔액은 음수가 될 수 없다 (포인트 정책 §2-6). 누적 획득 포인트도 회수로 0 아래로 내려가지 않는다
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_points_check CHECK (point_balance >= 0 AND lifetime_points >= 0)');

        // 포인트 정책 §3 제안값 (§10-5 확정). 관리자 화면에서 바꾼다.
        // 이벤트 보상·관리자 지급처럼 금액을 그때그때 정하는 사유는 행이 없다
        $now = now();
        DB::table('point_policies')->insert(array_map(fn (array $row) => [
            'action' => $row[0],
            'points' => $row[1],
            'daily_limit_count' => $row[2],
            'daily_limit_points' => $row[3],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['signup', 100, null, null],
            ['attendance', 10, 1, null],
            ['attendance_streak_7', 50, null, null],
            ['attendance_streak_30', 300, null, null],
            ['review_create', 100, 3, null],
            ['review_like_received', 5, null, 50],
            ['post_create', 20, 5, null],
            ['comment_create', 5, 10, null],
        ]));
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_points_check');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('point_policies');
    }
};
