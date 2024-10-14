<?php

namespace Prasso\Messaging\Filament\Resources\MsgCampaignsResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgCampaignsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMsgCampaigns extends ListRecords
{
    protected static string $resource = MsgCampaignsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
