<?php

namespace Prasso\Messaging\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Components;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Prasso\Messaging\Filament\Resources\MsgTeamSettingResource\Pages;
use Prasso\Messaging\Models\MsgTeamSetting;

class MsgTeamSettingResource extends Resource
{
    protected static ?string $model = MsgTeamSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Team Settings';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Section::make('WhatsApp')
                    ->schema([
                        Components\Toggle::make('whatsapp_enabled')
                            ->label('Enable WhatsApp')
                            ->default(false),
                        Components\TextInput::make('whatsapp_phone_number_id')
                            ->label('WhatsApp Phone Number ID')
                            ->maxLength(255),
                        Components\TextInput::make('whatsapp_business_account_id')
                            ->label('WhatsApp Business Account ID')
                            ->maxLength(255),
                        Components\Textarea::make('whatsapp_access_token')
                            ->label('WhatsApp Access Token')
                            ->rows(4),
                    ])
                    ->columns(1),

                Components\Section::make('SMS')
                    ->schema([
                        Components\TextInput::make('sms_from')
                            ->label('SMS From')
                            ->maxLength(255),
                        Components\Textarea::make('opt_in_confirmation_message')
                            ->label('Opt-in Confirmation Message')
                            ->helperText('Custom message sent to users when they opt-in for SMS. Use placeholders: {business}, {business_name}, {cap}, {monthly_cap}')
                            ->rows(4)
                            ->placeholder("You're almost done! Reply YES to confirm your {business} text notifications (up to {cap} messages/month). You'll receive appointment reminders, service updates, and occasional offers. Reply STOP to opt out, HELP for help. Msg & data rates may apply."),
                    ])
                    ->columns(1),

                Components\Section::make('Verification')
                    ->schema([
                        Components\Select::make('verification_status')
                            ->label('Verification Status')
                            ->options([
                                'unregistered' => 'Unregistered',
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                                'suspended' => 'Suspended',
                            ]),
                        Components\DateTimePicker::make('verified_at')
                            ->label('Verified At'),
                        Components\Textarea::make('verification_notes')
                            ->label('Verification Notes')
                            ->rows(3),
                    ])
                    ->columns(1),

                Components\Section::make('Message Recipients')
                    ->schema([
                        Components\CheckboxList::make('recipient_sources')
                            ->label('Allowed Recipient Sources')
                            ->options([
                                'users' => 'Registered Users',
                                'guests' => 'Guests',
                                'members' => 'Members',
                            ])
                            ->default(['users', 'guests', 'members']),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('team_id')->sortable(),
                Tables\Columns\TextColumn::make('verification_status')->badge()->sortable(),
                Tables\Columns\IconColumn::make('whatsapp_enabled')->boolean()->label('WhatsApp'),
                Tables\Columns\TextColumn::make('sms_from')->label('SMS From')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->since()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('team_id');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMsgTeamSettings::route('/'),
            'edit' => Pages\EditMsgTeamSetting::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $panel = \Filament\Facades\Filament::getCurrentPanel();
        $user = Auth::user();
        if (!$panel || !$user) {
            return false;
        }
        if ($panel->getId() === 'site-admin') {
            return true;
        }
        if ($panel->getId() === 'admin') {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        }
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        try {
            $panel = \Filament\Facades\Filament::getCurrentPanel();
            if ($panel && ($panel->getId() === 'admin' || $panel->getId() === 'site-admin') && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return $query;
            }
        } catch (\Throwable $e) {
        }

        $teamId = null;
        if (property_exists($user, 'current_team_id') && !empty($user->current_team_id)) {
            $teamId = $user->current_team_id;
        } elseif (property_exists($user, 'team_id') && !empty($user->team_id)) {
            $teamId = $user->team_id;
        } elseif (method_exists($user, 'currentTeam') && $user->currentTeam) {
            $teamId = $user->currentTeam->id ?? null;
        }

        if (!$teamId && method_exists($user, 'getUserOwnerSiteId') && class_exists(\App\Models\Site::class)) {
            try {
                $siteId = $user->getUserOwnerSiteId();
                $site = \App\Models\Site::find($siteId);
                $team = $site?->teams()->first();
                $teamId = $team?->id;
            } catch (\Throwable $e) {
            }
        }

        if ($teamId) {
            return $query->where('team_id', $teamId);
        }

        return $query->whereRaw('1 = 0');
    }
}
