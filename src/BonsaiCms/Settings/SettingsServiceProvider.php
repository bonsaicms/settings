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

        $this->app->singleton(Contracts\SettingsRepositoryFactory::class, $implementations[Contracts\SettingsRepositoryFactory::class]);

        /*
         * The repository is whichever driver "settings.default" names. Asking
         * the factory keeps the driver a runtime decision, so SETTINGS_DRIVER
         * (or a config change in a test) is enough to swap the backend.
         */
        $this->app->bind(Contracts\SettingsRepository::class, function ($app) {
            return $app->make(Contracts\SettingsRepositoryFactory::class)->driver();
        });

        $this->app->singleton(Contracts\SettingsManager::class, $implementations[Contracts\SettingsManager::class]);
    }

    /**
     * Bootstrap the settings package;
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../../config/settings.php' => config_path('settings.php'),
        ], ['settings', 'settings-config']);

        $this->publishesMigrations([
            __DIR__.'/../../../database/migrations' => database_path('migrations'),
        ], ['settings', 'settings-migrations']);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\DeleteAllSettingsCommand::class,
            ]);
        }
    }
}
