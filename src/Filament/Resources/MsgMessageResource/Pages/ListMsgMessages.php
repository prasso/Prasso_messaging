<?php

namespace Prasso\Messaging\Filament\Resources\MsgMessageResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgMessageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMsgMessages extends ListRecords
{
    protected static string $resource = MsgMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
