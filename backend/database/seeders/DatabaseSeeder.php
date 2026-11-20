<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // 로컬 확인용 회원. 소셜 로그인 없이는 로그인할 수 없으므로 화면 확인은 테스트나 tinker로 한다.
        User::factory()->create([
            'email' => 'test@example.com',
            'nickname' => '테스트회원',
        ]);
        User::factory()->pending()->create([
            'email' => 'pending@example.com',
        ]);

        // 장르·플랫폼 기본 목록은 마이그레이션이 넣는다
        $this->call(GameSeeder::class);
    }
}
