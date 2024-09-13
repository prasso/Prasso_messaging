<?php

namespace Prasso\Messaging\Filament\Resources\MsgEngagementResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgEngagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMsgEngagement extends EditRecord
{
    protected static string $resource = MsgEngagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
