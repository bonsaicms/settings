<?php

namespace BonsaiCms\Settings;

use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register the settings package.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../config/settings.php', 'settings'
        );

        $this->app->bind(
            Contracts\SettingsSerializer::class,
            fn ($app) => $app->make($this->implementationOf(Contracts\SettingsSerializer::class), [
                'throwExceptions' => (bool) config('settings.throwExceptions.serialize'),
            ])
        );

        $this->app->bind(
            Contracts\SettingsDeserializer::class,
            fn ($app) => $app->make($this->implementationOf(Contracts\SettingsDeserializer::class), [
                'throwExceptions' => (bool) config('settings.throwExceptions.deserialize'),
            ])
        );

        $this->app->singleton(
            Contracts\SettingsRepositoryFactory::class,
            fn ($app) => $app->make($this->implementationOf(Contracts\SettingsRepositoryFactory::class))
        );

        /*
         * The repository is whichever driver "settings.default" names. Asking
         * the factory keeps the driver a runtime decision, so SETTINGS_DRIVER
         * (or a config change in a test) is enough to swap the backend.
         */
        $this->app->bind(
            Contracts\SettingsRepository::class,
            fn ($app) => $app->make(Contracts\SettingsRepositoryFactory::class)->driver()
        );

        $this->app->singleton(
            Contracts\SettingsManager::class,
            fn ($app) => $app->make($this->implementationOf(Contracts\SettingsManager::class))
        );
    }

    /**
     * Bootstrap the settings package.
     */
    public function boot(): void
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

    /**
     * The class an application has picked for one of the seams.
     *
     * Read at bind time rather than once in register(), so a config change
     * made after the provider ran still takes effect - which is what lets a
     * test swap a piece without rebuilding the container.
     *
     * @param  class-string  $contract
     * @return class-string
     */
    protected function implementationOf(string $contract): string
    {
        return config("settings.bindings.{$contract}");
    }
}
