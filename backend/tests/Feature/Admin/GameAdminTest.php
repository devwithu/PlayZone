<?php

namespace Tests\Feature\Admin;

use App\Enums\GameImageType;
use App\Enums\GameStatus;
use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Games\RelationManagers\ReleaseDatesRelationManager;
use App\Filament\Resources\Genres\Pages\ManageGenres;
use App\Models\Game;
use App\Models\GameImage;
use App\Models\GameReleaseDate;
use App\Models\Genre;
use App\Models\Platform;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 관리자 게임·장르·플랫폼 관리 (ADM-02·03, WEB-103~105)
 */
class GameAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function userWithRole(string $slug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->value('id'));

        return $user;
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMIN));
    }

    private function platform(string $slug): Platform
    {
        return Platform::query()->where('slug', $slug)->sole();
    }

    // ---- 권한: admin 이상 ----

    public function test_moderator_cannot_manage_games_members_or_taxonomy(): void
    {
        $game = Game::factory()->create();
        $this->actingAs($this->userWithRole(Role::MODERATOR));

        $this->get('/admin')->assertOk();
        foreach (['/admin/games', "/admin/games/{$game->id}/edit", '/admin/genres', '/admin/platforms', '/admin/users'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_admin_opens_game_pages(): void
    {
        $game = Game::factory()->create();
        $this->actingAsAdmin();

        foreach (['/admin/games', '/admin/games/create', "/admin/games/{$game->id}/edit", '/admin/genres', '/admin/platforms', '/admin/users'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/games/abc/edit')->assertNotFound();
    }

    // ---- 게임 등록·수정 ----

    public function test_admin_creates_game_with_korean_slug_from_title(): void
    {
        $this->actingAsAdmin();
        $genreIds = Genre::query()->whereIn('slug', ['rpg', 'action'])->pluck('id')->all();

        Livewire::test(CreateGame::class)
            ->fillForm(['title' => '별빛 원정대: 두 번째 이야기'])
            ->assertSchemaStateSet(['slug' => '별빛-원정대-두-번째-이야기'])
            ->fillForm([
                'developer' => '스튜디오 별',
                'status' => GameStatus::Published->value,
                'genres' => $genreIds,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $game = Game::query()->where('slug', '별빛-원정대-두-번째-이야기')->sole();
        $this->assertSame(GameStatus::Published, $game->status);
        $this->assertEqualsCanonicalizing($genreIds, $game->genres()->pluck('genres.id')->all());
    }

    public function test_slug_must_be_unique_and_well_formed(): void
    {
        Game::factory()->create(['slug' => 'star-expedition']);
        $this->actingAsAdmin();

        Livewire::test(CreateGame::class)
            ->fillForm(['title' => '별빛 원정대', 'slug' => 'star-expedition'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        foreach (['Star Expedition', 'star_expedition', '-star', 'star--expedition'] as $slug) {
            Livewire::test(CreateGame::class)
                ->fillForm(['title' => '별빛 원정대', 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug' => 'regex']);
        }
    }

    public function test_edit_keeps_slug_and_has_no_delete(): void
    {
        $game = Game::factory()->create(['title' => '별빛 원정대', 'slug' => 'star-expedition']);
        $this->actingAsAdmin();

        Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])
            ->assertActionDoesNotExist('delete')
            ->fillForm(['title' => '별빛 원정대 리마스터', 'status' => GameStatus::Hidden->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $game->refresh();
        $this->assertSame('star-expedition', $game->slug);
        $this->assertSame(GameStatus::Hidden, $game->status);
    }

    public function test_game_list_filters_by_status(): void
    {
        $published = Game::factory()->create();
        $draft = Game::factory()->draft()->create();
        $this->actingAsAdmin();

        Livewire::test(ListGames::class)
            ->assertCanSeeTableRecords([$published, $draft])
            ->filterTable('status', GameStatus::Draft->value)
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$published]);
    }

    // ---- 출시일 ----

    public function test_admin_adds_release_dates_and_platform_is_attached(): void
    {
        $game = Game::factory()->create();
        $this->actingAsAdmin();

        Livewire::test(ReleaseDatesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
            ->callTableAction('create', data: [
                'platform_id' => $this->platform('ps5')->id,
                'release_status' => ReleaseStatus::Scheduled->value,
                'date_precision' => ReleaseDatePrecision::Quarter->value,
                'release_date' => '2027-05-20',
            ])
            ->assertHasNoTableActionErrors();

        $releaseDate = $game->releaseDates()->sole();
        $this->assertSame('2027-04-01', $releaseDate->release_date?->toDateString());
        $this->assertSame('2027년 2분기', $releaseDate->displayDate());
        $this->assertSame(['ps5'], $game->platforms()->pluck('slug')->all());
    }

    public function test_release_date_rules(): void
    {
        $game = Game::factory()->create();
        GameReleaseDate::factory()->for($game)->for($this->platform('pc'))->create();
        $this->actingAsAdmin();
        $manager = fn () => Livewire::test(ReleaseDatesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class]);

        // 같은 플랫폼은 한 번만
        $manager()->callTableAction('create', data: [
            'platform_id' => $this->platform('pc')->id,
            'release_status' => ReleaseStatus::Released->value,
            'date_precision' => ReleaseDatePrecision::Day->value,
            'release_date' => '2025-01-10',
        ])->assertHasTableActionErrors(['platform_id']);

        // 미정이 아니면 날짜 필수
        $manager()->callTableAction('create', data: [
            'platform_id' => $this->platform('ps5')->id,
            'release_status' => ReleaseStatus::Scheduled->value,
            'date_precision' => ReleaseDatePrecision::Month->value,
            'release_date' => null,
        ])->assertHasTableActionErrors(['release_date']);

        // 출시됨은 미정일 수 없음
        $manager()->callTableAction('create', data: [
            'platform_id' => $this->platform('ps5')->id,
            'release_status' => ReleaseStatus::Released->value,
            'date_precision' => ReleaseDatePrecision::Tba->value,
        ])->assertHasTableActionErrors(['release_status']);

        // 미정 + 발표는 날짜 없이 저장
        $manager()->callTableAction('create', data: [
            'platform_id' => $this->platform('switch-2')->id,
            'release_status' => ReleaseStatus::Announced->value,
            'date_precision' => ReleaseDatePrecision::Tba->value,
        ])->assertHasNoTableActionErrors();

        $this->assertSame(2, $game->releaseDates()->count());
    }

    // ---- 이미지 ----

    public function test_admin_uploads_cover_once(): void
    {
        Storage::fake(config('filesystems.default'));
        $game = Game::factory()->create();
        $this->actingAsAdmin();
        $manager = fn () => Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class]);

        $manager()->callTableAction('create', data: [
            'image_path' => UploadedFile::fake()->image('cover.jpg', 600, 800),
            'image_type' => GameImageType::Cover->value,
        ])->assertHasNoTableActionErrors();

        $cover = $game->images()->sole();
        $this->assertStringStartsWith("games/{$game->id}/", $cover->image_path);
        Storage::assertExists($cover->image_path);

        $manager()->callTableAction('create', data: [
            'image_path' => UploadedFile::fake()->image('cover2.jpg'),
            'image_type' => GameImageType::Cover->value,
        ])->assertHasTableActionErrors(['image_type']);

        $this->assertSame(1, $game->images()->count());
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        Storage::fake(config('filesystems.default'));
        $game = Game::factory()->create();
        $this->actingAsAdmin();

        // 실제 요청에서 Livewire는 파일 내용으로 형식을 판별한다(TemporaryUploadedFile::detectMimeTypeFromContents, REQ-905).
        // 테스트에서는 가짜 파일에 지정한 형식을 그대로 쓰므로, 허용하지 않는 형식이 거부되는지만 확인한다.
        foreach ([['doc.pdf', 'application/pdf'], ['anim.gif', 'image/gif'], ['shell.php', 'text/x-php']] as [$name, $mime]) {
            Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $game, 'pageClass' => EditGame::class])
                ->callTableAction('create', data: [
                    'image_path' => UploadedFile::fake()->create($name, 10, $mime),
                    'image_type' => GameImageType::Screenshot->value,
                ])
                ->assertHasTableActionErrors(['image_path']);
        }

        $this->assertSame(0, $game->images()->count());
    }

    public function test_deleting_or_replacing_image_removes_old_file(): void
    {
        Storage::fake(config('filesystems.default'));
        Storage::put('games/1/old.webp', 'old');
        Storage::put('games/1/new.webp', 'new');
        $image = GameImage::factory()->create(['image_path' => 'games/1/old.webp']);

        $image->update(['image_path' => 'games/1/new.webp']);
        Storage::assertMissing('games/1/old.webp');

        $image->delete();
        Storage::assertMissing('games/1/new.webp');
    }

    // ---- 장르·플랫폼 ----

    public function test_admin_adds_genre_at_end_with_valid_slug(): void
    {
        $this->actingAsAdmin();
        $lastOrder = Genre::query()->max('sort_order');

        Livewire::test(ManageGenres::class)
            ->callAction('create', data: ['name' => '배틀로얄', 'slug' => 'Battle Royale'])
            ->assertHasActionErrors(['slug' => 'regex']);

        Livewire::test(ManageGenres::class)
            ->callAction('create', data: ['name' => '배틀로얄', 'slug' => 'battle-royale'])
            ->assertHasNoActionErrors();

        $this->assertSame($lastOrder + 10, Genre::query()->where('slug', 'battle-royale')->value('sort_order'));
    }

    public function test_genre_in_use_cannot_be_deleted(): void
    {
        $used = Genre::query()->where('slug', 'rpg')->sole();
        Game::factory()->create()->genres()->attach($used);
        $unused = Genre::factory()->create();
        $this->actingAsAdmin();

        Livewire::test(ManageGenres::class)
            ->assertTableActionHidden('delete', $used)
            ->assertTableActionVisible('delete', $unused)
            ->callTableAction('delete', $unused);

        $this->assertModelMissing($unused);
    }
}
