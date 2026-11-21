<?php

namespace App\Filament\Resources\PointPolicies;

use App\Enums\PointType;
use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\PointPolicies\Pages\ManagePointPolicies;
use App\Models\PointPolicy;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * 포인트 금액·일일 한도 (포인트 정책 §3). admin 이상. 사유 목록은 코드(PointType)가 정하므로 추가·삭제는 없고 수정만 한다.
 * 바꾼 값은 다음 지급부터 적용된다. 이미 지급한 포인트는 바뀌지 않는다.
 */
class PointPolicyResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = PointPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = '포인트·등급';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = '포인트 정책';

    protected static ?string $pluralModelLabel = '포인트 정책';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('points')
                    ->label('지급 포인트')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1_000_000)
                    ->suffix('P'),
                Toggle::make('is_active')
                    ->label('지급함')
                    ->helperText('끄면 이 사유로는 포인트를 주지 않습니다.')
                    ->inline(false),
                TextInput::make('daily_limit_count')
                    ->label('하루 최대 횟수')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->suffix('회')
                    ->helperText('비우면 제한 없음. 한국 시간 0시 기준'),
                TextInput::make('daily_limit_points')
                    ->label('하루 최대 포인트')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(1_000_000)
                    ->suffix('P')
                    ->helperText('비우면 제한 없음. 넘으면 남은 만큼만 지급'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->columns([
                TextColumn::make('action')
                    ->label('사유')
                    ->formatStateUsing(fn (PointType $state) => $state->label())
                    ->description(fn (PointPolicy $record) => $record->action->value),
                TextColumn::make('points')->label('지급 포인트')->numeric()->suffix('P'),
                TextColumn::make('daily_limit_count')->label('하루 최대 횟수')->placeholder('제한 없음')->suffix('회'),
                TextColumn::make('daily_limit_points')->label('하루 최대 포인트')->placeholder('제한 없음')->numeric()->suffix('P'),
                IconColumn::make('is_active')->label('지급함')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePointPolicies::route('/'),
        ];
    }
}
