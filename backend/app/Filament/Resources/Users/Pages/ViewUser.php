<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserRoleService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 관리 역할 변경은 최고 관리자만 (ERD §3)
            Action::make('changeRoles')
                ->label('관리 역할 변경')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->visible(fn () => self::actor()->hasRole(Role::SUPER_ADMIN))
                ->fillForm(fn (User $record, UserRoleService $roles) => ['roles' => $roles->panelRoles($record)])
                ->schema([
                    CheckboxList::make('roles')
                        ->label('관리 역할')
                        ->options(fn () => Role::query()->whereIn('slug', Role::PANEL_ROLES)->orderBy('id')->pluck('name', 'slug')->all()),
                ])
                ->action(function (User $record, array $data, UserRoleService $roles, Action $action) {
                    try {
                        $roles->syncPanelRoles($record, array_values($data['roles'] ?? []), self::actor());
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        $action->halt();

                        return;
                    }

                    $record->load('roles');
                    Notification::make()->title('관리 역할을 변경했습니다.')->success()->send();
                }),
        ];
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
