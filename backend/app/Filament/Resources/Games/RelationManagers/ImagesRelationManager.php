<?php

namespace App\Filament\Resources\Games\RelationManagers;

use App\Enums\GameImageType;
use App\Models\Game;
use App\Models\GameImage;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * 게임 이미지 (WEB-103). 저장소에는 games/{게임 ID}/ 아래에 두고 DB에는 상대 경로만 저장한다.
 * 형식은 파일 내용 기준으로 검사한다 (REQ-905). 행을 지우거나 파일을 바꾸면 이전 파일도 지운다 (GameImage 모델).
 */
class ImagesRelationManager extends RelationManager
{
    /** 게임 이미지 최대 크기 (KB). 게시글 첨부와 같은 10MB (REQ-408) */
    public const MAX_KB = 10 * 1024;

    protected static string $relationship = 'images';

    protected static ?string $title = '이미지';

    protected static ?string $modelLabel = '이미지';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                FileUpload::make('image_path')
                    ->label('이미지')
                    ->image()
                    ->disk(config('filesystems.default'))
                    ->directory(fn () => 'games/'.$this->getOwnerRecord()->getKey())
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(self::MAX_KB)
                    ->required()
                    ->helperText('jpg·png·webp, 10MB 이하'),
                Select::make('image_type')
                    ->label('종류')
                    ->options(collect(GameImageType::cases())->mapWithKeys(fn (GameImageType $type) => [$type->value => $type->label()])->all())
                    ->default(GameImageType::Screenshot->value)
                    ->required()
                    ->rules([
                        // 표지는 게임당 하나 (DB 부분 UNIQUE 인덱스와 같은 규칙)
                        fn (?GameImage $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                            if ($value !== GameImageType::Cover->value) {
                                return;
                            }

                            $exists = $this->game()->images()
                                ->where('image_type', GameImageType::Cover)
                                ->when($record !== null, fn ($query) => $query->whereKeyNot($record?->getKey()))
                                ->exists();

                            if ($exists) {
                                $fail('표지가 이미 있습니다. 기존 표지를 바꾸거나 삭제해 주세요.');
                            }
                        },
                    ]),
            ]);
    }

    private function game(): Game
    {
        /** @var Game */
        return $this->getOwnerRecord();
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('이미지')
                    ->disk(config('filesystems.default'))
                    ->imageHeight(64),
                TextColumn::make('image_type')
                    ->label('종류')
                    ->badge()
                    ->formatStateUsing(fn (GameImageType $state) => $state->label())
                    ->color(fn (GameImageType $state) => $state === GameImageType::Cover ? 'primary' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
