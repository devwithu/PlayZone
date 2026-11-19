<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD §3 social_accounts / withdrawn_identity_hashes (ADR-003).
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->text('refresh_token')->nullable(); // 암호화 저장 (모델 cast)
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });

        DB::statement("ALTER TABLE social_accounts ADD CONSTRAINT social_accounts_provider_check CHECK (provider IN ('google', 'apple'))");

        Schema::create('withdrawn_identity_hashes', function (Blueprint $table) {
            $table->id();
            $table->char('identity_hash', 64)->unique();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawn_identity_hashes');
        Schema::dropIfExists('social_accounts');
    }
};
