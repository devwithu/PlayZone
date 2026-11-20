<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD §9 favorite_games: 관심 게임과 출시 알림(release_alert). 설계서의 release_alerts 테이블을 합쳤다.
     * games.favorite_count는 이 테이블의 집계 캐시이고 FavoriteService만 바꾼다.
     */
    public function up(): void
    {
        Schema::create('favorite_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->boolean('release_alert')->default(true);
            $table->timestampsTz();

            $table->unique(['user_id', 'game_id']);
            // 출시일이 바뀐 게임의 알림 대상 찾기 (4단계 알림)
            $table->index(['game_id', 'release_alert']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_games');
    }
};
