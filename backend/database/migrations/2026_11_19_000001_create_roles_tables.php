<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ERD §3 roles / role_user.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->timestampsTz();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        $now = now();
        DB::table('roles')->insert([
            ['name' => '회원', 'slug' => 'member', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '운영자', 'slug' => 'moderator', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '관리자', 'slug' => 'admin', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '최고 관리자', 'slug' => 'super_admin', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
