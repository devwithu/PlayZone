<?php

namespace App\Filament\Resources\Games\Tables;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Genre;
use App\Models\Platform;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cover', 'genres', 'platforms']))
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('cover.image_path')
                    ->label('표지')
                    ->disk(config('filesystems.default'))
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->label('제목')
                    ->description(fn (Game $record) => '/games/'.$record->slug)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('상태')
                    ->badge()
                    ->formatStateUsing(fn (GameStatus $state) => $state->label())
                    ->color(fn (GameStatus $state) => match ($state) {
                        GameStatus::Published => 'success',
                        GameStatus::Draft => 'warning',
                        GameStatus::Hidden => 'gray',
                    }),
                TextColumn::make('genres.name')
                    ->label('장르')
                    ->badge()
                    ->color('gray')
                    ->limitList(3),
                TextColumn::make('platforms.name')
                    ->label('플랫폼')
                    ->badge()
                    ->color('gray')
                    ->limitList(3),
                TextColumn::make('review_count')
                    ->label('리뷰')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('수정')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('상태')
                    ->options(collect(GameStatus::cases())->mapWithKeys(fn (GameStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('genres')
                    ->label('장르')
                    ->relationship('genres', 'name')
                    ->options(fn () => Genre::query()->ordered()->pluck('name', 'id')->all()),
                SelectFilter::make('platforms')
                    ->label('플랫폼')
                    ->relationship('platforms', 'name')
                    ->options(fn () => Platform::query()->ordered()->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
