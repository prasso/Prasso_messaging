<?php

namespace Prasso\Messaging\Filament\Resources;

use Prasso\Messaging\Filament\Resources\MsgCampaignResource\Pages;
use Prasso\Messaging\Models\MsgCampaign;
use Filament\Forms;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

   class MsgCampaignResource extends Resource
   {
       protected static ?string $model = MsgCampaign::class;
   
       public static function form(Form $form): Form
       {
           return $form->schema([
            Components\Hidden::make('id'),
           ]);
       }
       public static function getSlug(): string
        {
            return 'msg-campaign'; // override to force singular
        }
        public static function getResourceRouteGroup(): string
        {
            return 'prasso/messaging/filament';
        }
        
       public static function table(Table $table): Table
       {
           return $table->columns([
            Tables\Columns\TextColumn::make('id')
           ]);
       }
   
       public static function getPages(): array
       {
        

           return [
               'index' => Pages\ListMsgCampaigns::route('/'),
               'create' => Pages\CreateMsgCampaign::route('/create'),
               'edit' => Pages\EditMsgCampaign::route('/{record}/edit'),
           ];
       }
   }
   
