<?php

namespace Prasso\Messaging\Filament\Resources\MsgTeamSettingResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Prasso\Messaging\Filament\Resources\MsgTeamSettingResource;

class ListMsgTeamSettings extends ListRecords
{
    protected static string $resource = MsgTeamSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
