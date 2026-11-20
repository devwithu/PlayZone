<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apple 토큰 폐기(/auth/revoke)에는 토큰을 발급받은 client_id가 필요하다 (ADR-003 §5).
     * 웹 로그인은 Services ID, 앱 로그인은 앱 번들 ID로 서로 다르다.
     */
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->string('refresh_token_client_id')->nullable()->after('refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('refresh_token_client_id');
        });
    }
};
