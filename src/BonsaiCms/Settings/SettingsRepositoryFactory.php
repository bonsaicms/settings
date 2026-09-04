<?php

namespace BonsaiCms\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use BonsaiCms\Settings\Exceptions\UnsupportedDriverException;

/**
 * Resolves named settings drivers, the same way Laravel resolves cache stores
 * or filesystem disks.
 *
 * A driver is an entry in "settings.drivers": a name the application picks
 * plus a "driver" type that maps to a repository class through
 * "settings.driver_implementations". Two drivers may therefore share a type
 * and differ only in configuration - two Redis drivers writing to different
 * hashes, say.
 */
class SettingsRepositoryFactory implements Contracts\SettingsRepositoryFactory
{
    /**
     * Resolved drivers, keyed by driver name.
     *
     * @var array<string, Contracts\SettingsRepository>
     */
    protected array $drivers = [];

    public function __construct(
        protected readonly Container $container,
        protected readonly Config $config
    ) {
    }

    public function driver(?string $name = null): Contracts\SettingsRepository
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    public function getDefaultDriver(): string
    {
        /*
         * Not the second argument of get(): that one only applies to a key
         * that is absent, and "settings.default" never is - the provider
         * merges the config file, whose value is an env() call that answers
         * null as readily as a driver name.
         */
        return $this->config->get('settings.default') ?: 'database';
    }

    public function forgetDrivers(): void
    {
        $this->drivers = [];
    }

    protected function resolve(string $name): Contracts\SettingsRepository
    {
        $config = $this->config->get("settings.drivers.{$name}");

        if (! is_array($config)) {
            throw UnsupportedDriverException::undefined($name);
        }

        if (empty($config['driver'])) {
            throw UnsupportedDriverException::missingType($name);
        }

        $type = $config['driver'];

        $implementation = $this->config->get("settings.driver_implementations.{$type}");

        if (! $implementation) {
            throw UnsupportedDriverException::unknownType($name, $type);
        }

        return $this->container->make($implementation, [
            'config' => $config,
        ]);
    }
}
