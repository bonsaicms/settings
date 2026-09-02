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
