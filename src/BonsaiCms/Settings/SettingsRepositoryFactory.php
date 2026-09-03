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
    protected $container;

    protected $config;

    /**
     * Resolved drivers, keyed by driver name.
     *
     * @var array<string, Contracts\SettingsRepository>
     */
    protected $drivers = [];

    public function __construct(Container $container, Config $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    public function driver(?string $name = null) : Contracts\SettingsRepository
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->drivers[$name] ??= $this->resolve($name);
    }

    /**
     * The driver used when nothing is asked for by name.
     */
    public function getDefaultDriver() : string
    {
        return $this->config->get('settings.default', 'database');
    }

    /**
     * Forget the resolved instances, so the next driver() call rebuilds them
     * from the current configuration.
     */
    public function forgetDrivers() : void
    {
        $this->drivers = [];
    }

    protected function resolve(string $name) : Contracts\SettingsRepository
    {
        $config = $this->config->get("settings.drivers.{$name}");

        if ( ! is_array($config)) {
            throw UnsupportedDriverException::undefined($name);
        }

        if (empty($config['driver'])) {
            throw UnsupportedDriverException::missingType($name);
        }

        $type = $config['driver'];

        $implementation = $this->config->get("settings.driver_implementations.{$type}");

        if ( ! $implementation) {
            throw UnsupportedDriverException::unknownType($name, $type);
        }

        return $this->container->make($implementation, [
            'config' => $config,
        ]);
    }
}
