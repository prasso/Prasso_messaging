<?php

namespace Prasso\Messaging\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Prasso\Messaging\MessagingServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MessagingServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations($app)
    {
        // Load this package's migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getEnvironmentSetUp($app)
    {
        // Use in-memory sqlite
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Minimal messaging config for tests
        $app['config']->set('twilio.sid', 'test_sid');
        $app['config']->set('twilio.auth_token', 'test_token');
        $app['config']->set('twilio.phone_number', '+15005550006');
        $app['config']->set('messaging.sms_from', '+15005550006');
        $app['config']->set('messaging.rate_limit', [
            'per_guest_monthly_cap' => 0,
            'per_guest_window_days' => 30,
            'allow_transactional_bypass' => true,
        ]);
    }
}
