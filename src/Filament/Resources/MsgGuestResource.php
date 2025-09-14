<?php

namespace Prasso\Messaging\Filament\Resources;

use Prasso\Messaging\Filament\Resources\MsgGuestResource\Pages;
use Prasso\Messaging\Filament\Resources\MsgGuestResource\RelationManagers;
use Prasso\Messaging\Models\MsgGuest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MsgGuestResource extends Resource
{
    protected static ?string $model = MsgGuest::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Messaging';
    
    protected static ?string $navigationLabel = 'Guest List';
    
    protected static ?int $navigationSort = 30;
    
    protected static ?string $pluralModelLabel = 'Guest List';
   
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Hidden::make('id'),
                Components\TextInput::make('name')->required(),
                Components\TextInput::make('email')->email()->required(),
                Components\TextInput::make('phone'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('phone')
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
            'index' => Pages\ListMsgGuests::route('/'),
            'create' => Pages\CreateMsgGuest::route('/create'),
            'edit' => Pages\EditMsgGuest::route('/{record}/edit'),
        ];
    }
}
