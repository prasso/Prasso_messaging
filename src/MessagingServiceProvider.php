<?php
namespace Prasso\Messaging;

use Illuminate\Support\ServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Facades\Filament;
use Prasso\Messaging\Support\Facades\MessagingPanel;
use Prasso\Messaging\Filament\Pages;
use Prasso\Messaging\Filament\Resources;
use Livewire\Livewire;

class MessagingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish config file
        if (file_exists(__DIR__.'/../config/messaging.php')) {
            $this->publishes([
                __DIR__.'/../config/messaging.php' => config_path('messaging.php'),
            ], 'config');
        }
        
        // Publish Twilio config if it doesn't exist
        $this->publishes([
            __DIR__.'/../config/twilio.php' => config_path('twilio.php'),
        ], 'config');
    
        Filament::registerResources(MessagingPanel::getMessagingResources());

        Livewire::component('prasso.messaging.filament.resources.msg-campaign-resource.pages.edit-msg-campaign', \Prasso\Messaging\Filament\Resources\MsgCampaignResource\Pages\EditMsgCampaign::class);
        Livewire::component('prasso.messaging.filament.resources.msg-engagement-resource.pages.edit-msg-engagement', \Prasso\Messaging\Filament\Resources\MsgEngagementResource\Pages\EditMsgEngagement::class);
        Livewire::component('prasso.messaging.filament.resources.msg-guest-resource.pages.edit-msg-guest', \Prasso\Messaging\Filament\Resources\MsgGuestResource\Pages\EditMsgGuest::class);
        Livewire::component('prasso.messaging.filament.resources.msg-message-resource.pages.edit-msg-message', \Prasso\Messaging\Filament\Resources\MsgMessageResource\Pages\EditMsgMessage::class);
        Livewire::component('prasso.messaging.filament.resources.msg-workflow-resource.pages.edit-msg-workflow', \Prasso\Messaging\Filament\Resources\MsgWorkflowResource\Pages\EditMsgWorkflow::class);
   
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/messaging.php', 'messaging'
        );
        
        // Register services
        $this->app->scoped('messaging', function (): MessagingPanelManager {
            return new MessagingPanelManager();
        });
        
        // Register MessageService
        $this->app->singleton('messaging.message', function ($app) {
            return new \Prasso\Messaging\Services\MessageService();
        });
        
        // Register Twilio config
        $this->mergeConfigFrom(
            __DIR__.'/../config/twilio.php', 'twilio'
        );
    }
}