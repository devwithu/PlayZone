<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Enums\ReleaseDatePrecision;
use App\Enums\ReleaseStatus;
use App\Models\GameReleaseDate;
use App\Models\Platform;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * 플랫폼별 출시일 (WEB-105, ERD §4). 게임·플랫폼 조합당 하나.
 * 출시 알림 발송(WEB-105 "변경 시 출시 알림")은 알림 기능과 함께 4단계에서 붙인다.
 */
class ReleaseDatesRelationManager extends RelationManager
{
    protected static string $relationship = 'releaseDates';

    protected static ?string $title = '출시일';

    protected static ?string $modelLabel = '출시일';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('platform_id')
                    ->label('플랫폼')
                    ->options(fn () => Platform::query()->ordered()->pluck('name', 'id')->all())
                    ->required()
                    ->unique(
                        table: GameReleaseDate::class,
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('game_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages(['unique' => '이 플랫폼의 출시일이 이미 있습니다.']),
                Select::make('release_status')
                    ->label('출시 상태')
                    ->options(self::options(ReleaseStatus::cases()))
                    ->default(ReleaseStatus::Announced->value)
                    ->required()
                    ->live()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                            if ($value === ReleaseStatus::Released->value && $get('date_precision') === ReleaseDatePrecision::Tba->value) {
                                $fail('출시된 게임은 출시일이 미정일 수 없습니다.');
                            }
                        },
                    ]),
                Select::make('date_precision')
                    ->label('날짜 단위')
                    ->options(self::options(ReleaseDatePrecision::cases()))
                    ->default(ReleaseDatePrecision::Day->value)
                    ->required()
                    ->live()
                    ->helperText('월·분기·연 단위는 그 기간의 첫날로 저장되고 "2027년 2분기"처럼 표시됩니다.'),
                DatePicker::make('release_date')
                    ->label('출시일')
                    ->native(false)
                    ->displayFormat('Y-m-d')
                    ->required(fn (Get $get) => $get('date_precision') !== ReleaseDatePrecision::Tba->value)
                    ->hidden(fn (Get $get) => $get('date_precision') === ReleaseDatePrecision::Tba->value),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('platform'))
            ->defaultSort('release_date')
            ->columns([
                TextColumn::make('platform.name')->label('플랫폼'),
                TextColumn::make('release_date')
                    ->label('출시일')
                    ->state(fn (GameReleaseDate $record) => $record->displayDate())
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('release_date', $direction)),
                TextColumn::make('release_status')
                    ->label('출시 상태')
                    ->badge()
                    ->formatStateUsing(fn (ReleaseStatus $state) => $state->label())
                    ->color(fn (ReleaseStatus $state) => match ($state) {
                        ReleaseStatus::Released => 'success',
                        ReleaseStatus::Scheduled => 'info',
                        ReleaseStatus::Delayed => 'warning',
                        ReleaseStatus::Cancelled => 'danger',
                        ReleaseStatus::Announced => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  list<ReleaseStatus>|list<ReleaseDatePrecision>  $cases
     * @return array<string, string>
     */
    private static function options(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn (ReleaseStatus|ReleaseDatePrecision $case) => [$case->value => $case->label()])->all();
    }
}
