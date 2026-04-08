<?php

namespace Prasso\Messaging\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Prasso\Messaging\Models\MsgMessage;
use Illuminate\Database\Eloquent\Builder;

class RepliesReceivedResource extends Resource
{
    protected static ?string $model = MsgMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left';

    protected static ?string $navigationLabel = 'Replies Received';

    protected static ?string $breadcrumb = 'Replies Received';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'Messaging';

    public static function table(Table $table): Table
    {
        return $table
            ->query(static::getEloquentQuery())
            ->columns([
                Tables\Columns\TextColumn::make('body')
                    ->label('Message')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(200)
                    ->wrap(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'sms' => 'info',
                        'email' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent At')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('inboundMessages.count')
                    ->label('Replies Received')
                    ->counts('inboundMessages')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'sms' => 'SMS',
                        'email' => 'Email',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View Replies')
                    ->icon('heroicon-o-chat-bubble-left'),
            ])
            ->bulkActions([
                // No bulk actions for this report
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Super admins can see all messages
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $query->whereHas('inboundMessages');
        }

        // Regular users can only see messages from their own teams
        $teamIds = $user->teams()->pluck('teams.id')->toArray();

        if (empty($teamIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('team_id', $teamIds)->whereHas('inboundMessages');
    }

    public static function getPages(): array
    {
        return [
            'index' => \Prasso\Messaging\Filament\Resources\Pages\RepliesReceived\ListRepliesReceived::route('/'),
            'view' => \Prasso\Messaging\Filament\Resources\Pages\RepliesReceived\ViewReplies::route('/{record}'),
        ];
    }
}
