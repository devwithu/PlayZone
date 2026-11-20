<?php

namespace App\Filament\Resources\Games;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\Games\Pages\CreateGame;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Filament\Resources\Games\Pages\ListGames;
use App\Filament\Resources\Games\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Games\RelationManagers\ReleaseDatesRelationManager;
use App\Filament\Resources\Games\Schemas\GameForm;
use App\Filament\Resources\Games\Tables\GamesTable;
use App\Models\Game;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * 게임 관리 (ADM-02, WEB-103·105). admin 이상.
 * 삭제하지 않고 상태를 숨김으로 바꾼다 (ERD §1). 이미지·출시일은 아래 탭(관계 관리자)에서 관리한다.
 */
class GameResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = '게임 정보';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = '게임';

    protected static ?string $pluralModelLabel = '게임';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return GameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GamesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ReleaseDatesRelationManager::class,
            ImagesRelationManager::class,
        ];
    }

    /**
     * PostgreSQL은 bigint 칼럼에 숫자가 아닌 값을 넣으면 오류(500)를 내므로 바로 404로 처리한다.
     */
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        if (! ctype_digit((string) $key)) {
            return null;
        }

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGames::route('/'),
            'create' => CreateGame::route('/create'),
            'edit' => EditGame::route('/{record}/edit'),
        ];
    }
}
