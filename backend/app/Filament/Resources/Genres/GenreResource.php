<?php

namespace App\Filament\Resources\Genres;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\Genres\Pages\ManageGenres;
use App\Filament\Support\GameTaxonomy;
use App\Models\Genre;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 장르 관리 (ADM-03). admin 이상.
 */
class GenreResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = Genre::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = '게임 정보';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '장르';

    protected static ?string $pluralModelLabel = '장르';

    public static function form(Schema $schema): Schema
    {
        return GameTaxonomy::form($schema, Genre::class, 'genre');
    }

    public static function table(Table $table): Table
    {
        return GameTaxonomy::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGenres::route('/'),
        ];
    }
}
