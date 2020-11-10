<?php

namespace BonsaiCms\Settings;

use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register the settings package;
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/settings.php', 'settings'
        );

        $implementations = config('settings.implementations');

        $this->app->bind(Contracts\SettingsSerializer::class, $implementations[Contracts\SettingsSerializer::class]);
        $this->app->bind(Contracts\SettingsDeserializer::class, $implementations[Contracts\SettingsDeserializer::class]);
        $this->app->bind(Contracts\SettingsRepository::class, $implementations[Contracts\SettingsRepository::class]);

        $this->app->singleton(Contracts\SettingsManager::class, $implementations[Contracts\SettingsManager::class]);
    }

    /**
     * Bootstrap the settings package;
     *
     * @return void
     */
    public function boot()
    {
        require_once(__DIR__.'/../../../helpers/helpers.php');

        $this->publishes([
            __DIR__.'/../../../config/settings.php' => config_path('settings.php'),
        ], 'settings');

        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\DeleteAllSettingsCommand::class,
            ]);
        }
    }
}
