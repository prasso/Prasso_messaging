<?php
namespace Prasso\Messaging;

use Illuminate\Support\ServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Facades\Filament;
use Prasso\Messaging\Support\Facades\MessagingPanel;

class MessagingServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        // Load views
        $this->loadViewsFrom(__DIR__.'/views', 'messaging');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Publish config file
        $this->publishes([
            __DIR__.'/config/messaging.php' => config_path('messaging.php'),
        ]);


        Filament::registerResources(MessagingPanel::getMessagingResources());
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/config/messaging.php', 'messaging'
        );
        $this->app->scoped('messaging', function (): MessagingPanelManager {
            return new MessagingPanelManager();
        });
    }
}