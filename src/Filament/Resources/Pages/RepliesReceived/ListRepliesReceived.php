<?php

namespace Prasso\Messaging\Filament\Resources\Pages\RepliesReceived;

use Filament\Resources\Pages\ListRecords;
use Prasso\Messaging\Filament\Resources\RepliesReceivedResource;

class ListRepliesReceived extends ListRecords
{
    protected static string $resource = RepliesReceivedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action for this report
        ];
    }
}
