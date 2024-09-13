<?php

namespace Prasso\Messaging\Filament\Resources\MsgCampaignResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMsgCampaign extends EditRecord
{
    protected static string $resource = MsgCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
