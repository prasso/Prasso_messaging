<?php

namespace Prasso\Messaging\Filament\Resources\Pages\RepliesReceived;

use Filament\Resources\Pages\ViewRecord;
use Prasso\Messaging\Filament\Resources\RepliesReceivedResource;
use Prasso\Messaging\Models\MsgMessage;
use Prasso\Messaging\Models\MsgInboundMessage;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

class ViewReplies extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = RepliesReceivedResource::class;

    protected static string $view = 'prasso-messaging::filament.resources.pages.replies-received.view-replies';

    public function getTitle(): string
    {
        return "Replies to: {$this->record->subject}";
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->query(
                MsgInboundMessage::query()
                    ->whereHas('delivery', function ($query) {
                        $query->where('msg_message_id', $this->record->id);
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('from')
                    ->label('From')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('body')
                    ->label('Reply Message')
                    ->wrap()
                    ->limit(100)
                    ->searchable(),

                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received At')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('guest.name')
                    ->label('Guest Name')
                    ->getStateUsing(fn (MsgInboundMessage $record) => $record->guest?->name ?? 'Unknown'),
            ])
            ->filters([
                // Add filters if needed
            ])
            ->actions([
                Tables\Actions\Action::make('viewFull')
                    ->label('View Full')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->modalContent(fn (MsgInboundMessage $record) => view('prasso-messaging::filament.resources.pages.replies-received.reply-modal', ['reply' => $record]))
                    ->modalHeading('Full Reply')
                    ->modalWidth('2xl'),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('received_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
