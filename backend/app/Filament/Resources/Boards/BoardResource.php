<?php

namespace App\Filament\Resources\Boards;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\Boards\Pages\ManageBoards;
use App\Models\Board;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * 게시판 관리 (ADM-10, WEB-113): 추가·순서·사용 여부. admin 이상.
 * 글이 있는 게시판은 지우지 않고 사용을 끈다. 끄면 게시판과 그 글이 사이트에서 보이지 않는다.
 */
class BoardResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = Board::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = '커뮤니티';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = '게시판';

    protected static ?string $pluralModelLabel = '게시판';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('이름')->required()->maxLength(50),
                TextInput::make('slug')
                    ->label('주소용 이름(slug)')
                    ->required()
                    ->maxLength(50)
                    ->regex(Board::SLUG_PATTERN)
                    ->unique(ignoreRecord: true)
                    ->helperText('영문 소문자·숫자·하이픈(-)만. 주소 /community/{slug}에 쓰이므로 정한 뒤에는 바꾸지 마세요.'),
                TextInput::make('description')->label('설명')->maxLength(200)->columnSpanFull(),
                Toggle::make('is_active')->label('사용')->default(true),
                Hidden::make('sort_order')->default(fn () => ((int) Board::query()->max('sort_order')) + 10),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('이름'),
                TextColumn::make('slug')->label('slug')->color('gray'),
                TextColumn::make('posts_count')->label('글 수')->counts('posts')->numeric(),
                IconColumn::make('is_active')->label('사용')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Board $record) => $record->posts()->exists()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBoards::route('/'),
        ];
    }
}
