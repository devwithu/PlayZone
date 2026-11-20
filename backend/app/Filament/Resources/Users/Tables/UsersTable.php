<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\LoginProvider;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['socialAccounts', 'roles']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('nickname')
                    ->label('닉네임')
                    ->placeholder('(미설정)')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('이메일')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('상태')
                    ->badge()
                    ->formatStateUsing(fn (UserStatus $state) => $state->label())
                    ->color(fn (UserStatus $state) => $state->color()),
                TextColumn::make('login_methods')
                    ->label('로그인')
                    ->state(fn (User $record) => $record->socialAccounts
                        ->map(fn (SocialAccount $account) => $account->provider->label())
                        ->all())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('panel_roles')
                    ->label('관리 역할')
                    ->state(fn (User $record) => $record->roles
                        ->whereIn('slug', Role::PANEL_ROLES)
                        ->pluck('name')
                        ->all())
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('point_balance')
                    ->label('포인트')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('가입일')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('상태')
                    ->options(collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $status) => [$status->value => $status->label()])->all()),
                SelectFilter::make('provider')
                    ->label('로그인')
                    ->options(collect(LoginProvider::cases())->mapWithKeys(fn (LoginProvider $provider) => [$provider->value => $provider->label()])->all())
                    ->query(fn (Builder $query, array $data) => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('socialAccounts', fn (Builder $q) => $q->where('provider', $data['value']))),
                SelectFilter::make('panel_role')
                    ->label('관리 역할')
                    ->options(fn () => Role::query()->whereIn('slug', Role::PANEL_ROLES)->orderBy('id')->pluck('name', 'slug')->all())
                    ->query(fn (Builder $query, array $data) => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('roles', fn (Builder $q) => $q->where('slug', $data['value']))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
