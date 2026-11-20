<?php

namespace App\Filament\Resources\Games\Schemas;

use App\Enums\GameStatus;
use App\Models\Game;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('기본 정보')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('제목')
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true)
                            // 새로 등록할 때만 제목으로 주소를 채운다 (공개 후 주소가 바뀌지 않도록)
                            ->afterStateUpdated(function (string $operation, ?string $state, Get $get, Set $set) {
                                if ($operation === 'create' && blank($get('slug')) && filled($state)) {
                                    $set('slug', Game::slugFromTitle($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('주소(slug)')
                            ->required()
                            ->maxLength(200)
                            ->regex(Game::SLUG_PATTERN)
                            ->unique(ignoreRecord: true)
                            ->prefix('/games/')
                            ->helperText('영문 소문자·숫자·한글을 하이픈(-)으로 잇습니다. 공개한 뒤 바꾸면 기존 링크가 끊깁니다.'),
                        Select::make('status')
                            ->label('노출 상태')
                            ->options(collect(GameStatus::cases())->mapWithKeys(fn (GameStatus $status) => [$status->value => $status->label()])->all())
                            ->default(GameStatus::Draft->value)
                            ->required()
                            ->helperText('작성 중·숨김 게임은 사이트와 앱에 나오지 않습니다.'),
                        TextInput::make('developer')
                            ->label('개발사')
                            ->maxLength(100),
                        TextInput::make('publisher')
                            ->label('배급사')
                            ->maxLength(100),
                        Textarea::make('description')
                            ->label('소개')
                            ->rows(6)
                            ->columnSpanFull(),
                    ]),
                Section::make('분류')
                    ->columns(2)
                    ->schema([
                        Select::make('genres')
                            ->label('장르')
                            ->relationship('genres', 'name', fn ($query) => $query->reorder()->orderBy('sort_order')->orderBy('genres.id'))
                            ->multiple()
                            ->preload(),
                        Select::make('platforms')
                            ->label('플랫폼')
                            ->relationship('platforms', 'name', fn ($query) => $query->reorder()->orderBy('sort_order')->orderBy('platforms.id'))
                            ->multiple()
                            ->preload()
                            ->helperText('출시일을 등록한 플랫폼은 자동으로 추가됩니다.'),
                    ]),
            ]);
    }
}
