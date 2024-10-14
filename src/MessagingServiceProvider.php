<?php
namespace Prasso\Messaging;

use Illuminate\Support\ServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Facades\Filament;
use Prasso\Messaging\Support\Facades\MessagingPanel;
use Prasso\Messaging\Filament\Pages;
use Prasso\Messaging\Filament\Resources;

class MessagingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
       // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');


        // Publish config file
        if (file_exists(__DIR__.'/../config/messaging.php')) {
            $this->publishes([
                __DIR__.'/../config/messaging.php' => config_path('messaging.php'),
            ], 'config');
        }
    
        Filament::registerResources(MessagingPanel::getMessagingResources());
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/messaging.php', 'messaging'
        );
        $this->app->scoped('messaging', function (): MessagingPanelManager {
            return new MessagingPanelManager();
        });
    }
}