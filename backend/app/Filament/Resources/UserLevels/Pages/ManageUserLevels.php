<?php

namespace App\Filament\Resources\UserLevels\Pages;

use App\Filament\Resources\UserLevels\UserLevelResource;
use App\Jobs\RecalculateUserLevels;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUserLevels extends ManageRecords
{
    protected static string $resource = UserLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(fn () => RecalculateUserLevels::dispatch())
                ->successNotificationTitle('추가했습니다. 회원 등급을 다시 계산합니다.'),
        ];
    }
}
