<?php

namespace Prasso\Messaging\Filament\Resources\MsgTeamSettingResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Prasso\Messaging\Filament\Resources\MsgTeamSettingResource;

class EditMsgTeamSetting extends EditRecord
{
    protected static string $resource = MsgTeamSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
