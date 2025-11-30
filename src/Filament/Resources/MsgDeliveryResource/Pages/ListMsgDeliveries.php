<?php

namespace Prasso\Messaging\Filament\Resources\MsgDeliveryResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgDeliveryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMsgDeliveries extends ListRecords
{
    protected static string $resource = MsgDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions can be added here if needed
        ];
    }
}
