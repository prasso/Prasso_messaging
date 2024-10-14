<?php

namespace Prasso\Messaging\Filament\Resources\MsgCampaignsResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgCampaignsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMsgCampaign extends EditRecord
{
    protected static string $resource = MsgCampaignsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
