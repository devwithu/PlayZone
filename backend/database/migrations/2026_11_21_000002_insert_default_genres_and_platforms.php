<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 기본 장르·플랫폼 (roles와 같이 마이그레이션으로 넣어 운영 배포 때 함께 만든다).
     * 이후 추가·이름 변경은 관리자 화면에서 한다. slug는 필터 쿼리(?genre=, ?platform=)에 쓰이므로 바꾸지 않는다.
     */
    public function up(): void
    {
        $now = now();

        $genres = [
            ['액션', 'action'],
            ['어드벤처', 'adventure'],
            ['RPG', 'rpg'],
            ['MMORPG', 'mmorpg'],
            ['슈팅', 'shooter'],
            ['전략', 'strategy'],
            ['시뮬레이션', 'simulation'],
            ['스포츠', 'sports'],
            ['레이싱', 'racing'],
            ['격투', 'fighting'],
            ['퍼즐', 'puzzle'],
            ['플랫포머', 'platformer'],
            ['로그라이크', 'roguelike'],
            ['호러', 'horror'],
            ['샌드박스', 'sandbox'],
            ['리듬', 'rhythm'],
            ['캐주얼', 'casual'],
        ];

        $platforms = [
            ['PC', 'pc'],
            ['PlayStation 5', 'ps5'],
            ['PlayStation 4', 'ps4'],
            ['Xbox Series X|S', 'xbox-series'],
            ['Xbox One', 'xbox-one'],
            ['Nintendo Switch 2', 'switch-2'],
            ['Nintendo Switch', 'switch'],
            ['Android', 'android'],
            ['iOS', 'ios'],
        ];

        DB::table('genres')->insert(array_map(fn (array $row, int $i) => [
            'name' => $row[0], 'slug' => $row[1], 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
        ], $genres, array_keys($genres)));

        DB::table('platforms')->insert(array_map(fn (array $row, int $i) => [
            'name' => $row[0], 'slug' => $row[1], 'sort_order' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
        ], $platforms, array_keys($platforms)));
    }

    public function down(): void
    {
        DB::table('genres')->delete();
        DB::table('platforms')->delete();
    }
};
