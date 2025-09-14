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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MsgMessageResource extends Resource
{
    protected static ?string $model = MsgMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Messaging';
    
    protected static ?string $navigationLabel = 'Messages';
    
    protected static ?int $navigationSort = 40;
    
    protected static ?string $pluralModelLabel = 'Messages';
    
    protected static bool $shouldRegisterNavigation = false;

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
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(50),
                Tables\Columns\BadgeColumn::make('type'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->since(),
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
