<?php

namespace Prasso\Messaging\Filament\Resources;

use Prasso\Messaging\Filament\Resources\MsgEngagementResource\Pages;
use Prasso\Messaging\Filament\Resources\MsgEngagementResource\RelationManagers;
use Prasso\Messaging\Models\MsgEngagement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MsgEngagementResource extends Resource
{
    protected static ?string $model = MsgEngagement::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static ?string $navigationGroup = 'Messaging';
    
    protected static ?string $navigationColor = 'info';
    
    protected static ?string $navigationLabel = 'Customer Interactions';
    
    protected static ?int $navigationSort = 20;
    
    protected static ?string $pluralModelLabel = 'Customer Interactions';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Hidden::make('id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
            ])
            ->filters([
                //
            ])
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
            'index' => Pages\ListMsgEngagements::route('/'),
            'create' => Pages\CreateMsgEngagement::route('/create'),
            'edit' => Pages\EditMsgEngagement::route('/{record}/edit'),
        ];
    }
}
