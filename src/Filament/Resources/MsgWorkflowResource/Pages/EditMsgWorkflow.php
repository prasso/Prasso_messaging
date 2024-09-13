<?php

namespace Prasso\Messaging\Filament\Resources\MsgWorkflowResource\Pages;

use Prasso\Messaging\Filament\Resources\MsgWorkflowResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMsgWorkflow extends EditRecord
{
    protected static string $resource = MsgWorkflowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
