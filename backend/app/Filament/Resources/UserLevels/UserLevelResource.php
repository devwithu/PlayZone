<?php

namespace App\Filament\Resources\UserLevels;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\UserLevels\Pages\ManageUserLevels;
use App\Jobs\RecalculateUserLevels;
use App\Models\UserLevel;
use App\Services\UserLevelService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 회원 등급 관리 (포인트 정책 §9). admin 이상.
 * 구간을 추가·변경하면 전체 회원 등급을 큐 작업으로 다시 계산하고, 삭제하면 그 등급 회원을 바로 아래 등급으로 옮긴다.
 * 기본 등급(0P)은 기준을 바꾸거나 지울 수 없다.
 */
class UserLevelResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = UserLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = '포인트·등급';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = '회원 등급';

    protected static ?string $pluralModelLabel = '회원 등급';

    public const ICON_MAX_KB = 1024;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('이름')
                    ->required()
                    ->maxLength(30)
                    ->unique(ignoreRecord: true),
                TextInput::make('min_points')
                    ->label('기준 누적 포인트')
                    ->required()
                    ->integer()
                    ->minValue(fn (?UserLevel $record) => $record?->isBase() ? 0 : 1)
                    ->maxValue(100_000_000)
                    ->suffix('P')
                    ->unique(ignoreRecord: true)
                    // 기본 등급은 항상 0P여야 한다. 비활성 필드는 저장되지 않는다
                    ->disabled(fn (?UserLevel $record) => $record?->isBase() === true)
                    ->helperText(fn (?UserLevel $record) => $record?->isBase() === true
                        ? '기본 등급은 0P로 고정입니다.'
                        : '누적 획득 포인트가 이 값 이상이면 이 등급이 됩니다. 저장하면 전체 회원 등급을 다시 계산합니다.'),
                FileUpload::make('icon_path')
                    ->label('아이콘')
                    ->image()
                    ->disk(config('filesystems.default'))
                    ->directory('levels')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/png', 'image/webp', 'image/svg+xml'])
                    ->maxSize(self::ICON_MAX_KB)
                    ->helperText('png·webp·svg, 1MB 이하 (선택)')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('min_points')
            ->paginated(false)
            ->columns([
                ImageColumn::make('icon_path')
                    ->label('아이콘')
                    ->disk(config('filesystems.default'))
                    ->imageHeight(32),
                TextColumn::make('name')->label('이름'),
                TextColumn::make('min_points')
                    ->label('기준 누적 포인트')
                    ->numeric()
                    ->suffix('P'),
                TextColumn::make('users_count')
                    ->label('회원 수')
                    ->counts('users')
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn () => RecalculateUserLevels::dispatch())
                    ->successNotificationTitle('저장했습니다. 회원 등급을 다시 계산합니다.'),
                DeleteAction::make()
                    ->hidden(fn (UserLevel $record) => $record->isBase())
                    ->modalDescription('이 등급의 회원은 바로 아래 등급으로 옮겨집니다.')
                    ->using(function (UserLevel $record): bool {
                        app(UserLevelService::class)->delete($record);

                        return true;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUserLevels::route('/'),
        ];
    }
}
