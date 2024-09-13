<?php

namespace Prasso\Messaging\Filament\Resources\MsgGuestResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgGuestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMsgGuest extends EditRecord
{
    protected static string $resource = MsgGuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
