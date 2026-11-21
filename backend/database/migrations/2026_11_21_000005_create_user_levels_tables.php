<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD §8 등급: user_levels, users.user_level_id, user_level_histories.
     * 등급은 누적 획득 포인트(lifetime_points) 기준이다 (포인트 정책 §9). 이름·구간은 관리자가 바꾼다.
     */
    public function up(): void
    {
        Schema::create('user_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->integer('min_points')->unique();
            $table->string('icon_path', 255)->nullable();
            $table->timestampsTz();
        });

        // 기본 등급(0P)은 항상 있어야 한다. 앱에서 삭제·기준 변경을 막고, 음수 기준은 DB가 막는다
        DB::statement('ALTER TABLE user_levels ADD CONSTRAINT user_levels_min_points_check CHECK (min_points >= 0)');

        Schema::table('users', function (Blueprint $table) {
            // 등급을 지울 때는 회원을 바로 아래 등급으로 옮긴 뒤 지운다 (UserLevelService::delete)
            $table->foreignId('user_level_id')->nullable()->after('lifetime_points')->constrained('user_levels')->restrictOnDelete();
        });

        Schema::create('user_level_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('old_level_id')->nullable()->constrained('user_levels')->nullOnDelete();
            $table->foreignId('new_level_id')->nullable()->constrained('user_levels')->nullOnDelete();
            $table->string('reason', 20);
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
        });

        DB::statement("ALTER TABLE user_level_histories ADD CONSTRAINT user_level_histories_reason_check CHECK (reason IN ('earn', 'revoke', 'admin'))");

        // 시작 등급 (2026-11-21 결정). 관리자 화면에서 바꾼다
        $now = now();
        DB::table('user_levels')->insert(array_map(fn (array $row) => [
            'name' => $row[0],
            'min_points' => $row[1],
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['브론즈', 0],
            ['실버', 1000],
            ['골드', 4000],
            ['플래티넘', 10000],
        ]));

        // 이미 있는 회원의 등급을 누적 포인트로 정한다
        DB::statement('UPDATE users SET user_level_id = (SELECT l.id FROM user_levels l WHERE l.min_points <= users.lifetime_points ORDER BY l.min_points DESC LIMIT 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_level_histories');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_level_id');
        });
        Schema::dropIfExists('user_levels');
    }
};
