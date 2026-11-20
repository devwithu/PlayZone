<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('기본 정보')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('nickname')->label('닉네임')->placeholder('(미설정)'),
                        TextEntry::make('email')->label('이메일')->placeholder('-'),
                        TextEntry::make('status')
                            ->label('상태')
                            ->badge()
                            ->formatStateUsing(fn (UserStatus $state) => $state->label())
                            ->color(fn (UserStatus $state) => $state->color()),
                        TextEntry::make('panel_roles')
                            ->label('관리 역할')
                            ->state(fn (User $record) => $record->roles
                                ->whereIn('slug', Role::PANEL_ROLES)
                                ->pluck('name')
                                ->all())
                            ->badge()
                            ->placeholder('없음'),
                        TextEntry::make('created_at')->label('계정 생성')->dateTime('Y-m-d H:i'),
                    ]),
                Section::make('포인트')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('point_balance')->label('보유 포인트')->numeric(),
                        TextEntry::make('lifetime_points')->label('누적 포인트')->numeric(),
                    ]),
                Section::make('가입·동의')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('signup_completed_at')->label('가입 완료')->dateTime('Y-m-d H:i')->placeholder('미완료'),
                        TextEntry::make('terms_agreed_at')->label('약관 동의')->dateTime('Y-m-d H:i')->placeholder('-'),
                        TextEntry::make('age_confirmed_at')->label('만 14세 이상 확인')->dateTime('Y-m-d H:i')->placeholder('-'),
                        TextEntry::make('marketing_agreed_at')->label('마케팅 수신 동의')->dateTime('Y-m-d H:i')->placeholder('미동의'),
                        TextEntry::make('withdrawn_at')->label('탈퇴')->dateTime('Y-m-d H:i')->placeholder('-'),
                    ]),
                Section::make('로그인 연결')
                    ->schema([
                        TextEntry::make('login_methods')
                            ->hiddenLabel()
                            ->state(fn (User $record) => $record->socialAccounts
                                ->sortBy('id')
                                ->map(fn (SocialAccount $account) => sprintf(
                                    '%s · %s 연결',
                                    $account->provider->label(),
                                    $account->created_at?->setTimezone('Asia/Seoul')->format('Y-m-d H:i') ?? '-',
                                ))
                                ->values()
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder('연결된 계정 없음'),
                    ]),
            ]);
    }
}
