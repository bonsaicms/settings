<?php

namespace Tests;

use BonsaiCms\Settings\SettingsFacade;
use BonsaiCms\Settings\SettingsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Boots the package inside a real (in memory) Laravel application, so the
 * repositories are exercised against an actual database connection instead
 * of a mock.
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            SettingsServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Settings' => SettingsFacade::class,
        ];
    }

    /**
     * The package does not register its migration with the application; it is
     * published into the host app instead, so tests have to load it themselves.
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
