<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ERD §3 users. 지금은 Google·Apple 로그인만 쓰지만, 이메일·비밀번호 가입을
     * 나중에 추가할 수 있도록 password·email_verified_at·password_reset_tokens를 유지한다 (ADR-003 §8).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->unique();
            $table->timestampTz('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('nickname', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('point_balance')->default(0);
            $table->unsignedInteger('lifetime_points')->default(0);
            $table->timestampTz('signup_completed_at')->nullable();
            $table->timestampTz('terms_agreed_at')->nullable();
            $table->timestampTz('age_confirmed_at')->nullable();
            $table->timestampTz('marketing_agreed_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->rememberToken();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'active', 'suspended', 'withdrawn'))");
        DB::statement('CREATE UNIQUE INDEX users_nickname_lower_unique ON users (lower(nickname))');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
