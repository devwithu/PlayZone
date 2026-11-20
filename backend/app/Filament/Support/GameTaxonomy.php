<?php

namespace App\Filament\Support;

use App\Models\Genre;
use App\Models\Platform;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * 장르·플랫폼 관리 화면 공통 (ADM-03, WEB-104). 두 테이블은 구조(name, slug, sort_order)가 같다.
 */
class GameTaxonomy
{
    /**
     * @param  class-string<Genre|Platform>  $model
     */
    public static function form(Schema $schema, string $model, string $queryKey): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('이름')
                    ->required()
                    ->maxLength(50),
                TextInput::make('slug')
                    ->label('주소용 이름(slug)')
                    ->required()
                    ->maxLength(50)
                    ->regex($model::SLUG_PATTERN)
                    ->unique(ignoreRecord: true)
                    ->helperText("영문 소문자·숫자·하이픈(-)만 씁니다. 필터 주소(?{$queryKey}=...)에 쓰이므로 정한 뒤에는 바꾸지 마세요."),
                // 새 항목은 맨 뒤에 붙인다. 순서는 목록에서 끌어서 바꾼다
                Hidden::make('sort_order')
                    ->default(fn () => ((int) $model::query()->max('sort_order')) + 10),
            ]);
    }

    public static function table(Table $table): Table
    {
        // 게임이나 출시일에 쓰이고 있으면 삭제할 수 없다 (FK RESTRICT)
        $inUse = fn (Genre|Platform $record): bool => $record->games()->exists()
            || ($record instanceof Platform && $record->releaseDates()->exists());

        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('이름')->searchable(),
                TextColumn::make('slug')->label('slug')->color('gray'),
                TextColumn::make('games_count')->label('게임 수')->counts('games')->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Genre|Platform $record) => $inUse($record)),
            ]);
    }
}
