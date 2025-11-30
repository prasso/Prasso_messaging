<?php

namespace Prasso\Messaging\Filament\Resources;

use Prasso\Messaging\Filament\Resources\MsgMessageResource\Pages;
use Prasso\Messaging\Filament\Resources\MsgMessageResource\RelationManagers;
use Prasso\Messaging\Models\MsgMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MsgMessageResource extends Resource
{
    protected static ?string $model = MsgMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Messaging';
    
    protected static ?string $navigationLabel = 'Messages';
    
    protected static ?int $navigationSort = 21;
    
    protected static ?string $pluralModelLabel = 'Messages';
    
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\TextInput::make('subject')->required()->maxLength(255),
                Components\Select::make('type')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push',
                        'inapp' => 'In-App',
                    ])
                    ->required(),
                Components\Textarea::make('body')->rows(6)->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(50),
                Tables\Columns\TextColumn::make('body')->searchable()->limit(80)->wrap(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'sms' => 'info',
                        'email' => 'primary',
                        'voice' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('deliveries_count')
                    ->label('Deliveries')
                    ->counts('deliveries')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since()->sortable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('View Replies')
                    ->html()
                    ->formatStateUsing(fn ($record) => '<a href="' . route('message-conversations.show', ['messageId' => $record->id]) . '" class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium bg-green-100 text-green-800 hover:bg-green-200">View All</a>')
                    ->sortable(false)
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'sms' => 'SMS',
                        'email' => 'Email',
                        'voice' => 'Voice',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMsgMessages::route('/'),
            'create' => Pages\CreateMsgMessage::route('/create'),
            'edit' => Pages\EditMsgMessage::route('/{record}/edit'),
        ];
    }
}
