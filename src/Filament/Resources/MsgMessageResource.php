<?php

namespace Prasso\Messaging\Filament\Resources;

use Prasso\Messaging\Filament\Resources\MsgMessageResource\Pages;
use Prasso\Messaging\Filament\Resources\MsgMessageResource\RelationManagers;
use Prasso\Messaging\Models\MsgMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MsgMessageResource extends Resource
{
    protected static ?string $model = MsgMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
            'index' => Pages\ListMsgMessages::route('/'),
            'create' => Pages\CreateMsgMessage::route('/create'),
            'edit' => Pages\EditMsgMessage::route('/{record}/edit'),
        ];
    }
}
