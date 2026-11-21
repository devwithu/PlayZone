<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 회원 프로필 (ERD §3 user_profiles, REQ-602). 회원 1:1, 처음 저장할 때 만든다.
 * 프로필 이미지는 public 디스크의 상대 경로만 저장한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('profile_image_path', 255)->nullable();
            $table->string('introduction', 300)->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
