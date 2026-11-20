<?php

namespace App\Filament\Resources\Platforms;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\Platforms\Pages\ManagePlatforms;
use App\Filament\Support\GameTaxonomy;
use App\Models\Platform;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 플랫폼 관리 (ADM-03). admin 이상.
 */
class PlatformResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = Platform::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = '게임 정보';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = '플랫폼';

    protected static ?string $pluralModelLabel = '플랫폼';

    public static function form(Schema $schema): Schema
    {
        return GameTaxonomy::form($schema, Platform::class, 'platform');
    }

    public static function table(Table $table): Table
    {
        return GameTaxonomy::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePlatforms::route('/'),
        ];
    }
}
