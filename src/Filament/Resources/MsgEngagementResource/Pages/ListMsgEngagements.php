<?php

namespace Prasso\Messaging\Filament\Resources\MsgEngagementResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMsgEngagements extends ListRecords
{
    protected static string $resource = MsgEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
