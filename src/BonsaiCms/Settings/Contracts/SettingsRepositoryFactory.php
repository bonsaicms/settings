<?php

namespace BonsaiCms\Settings\Contracts;

interface SettingsRepositoryFactory
{
    /**
     * Resolve one of the configured drivers.
     *
     * Passing null resolves the driver named by "settings.default". The same
     * instance is returned for the same name, so an in-memory driver keeps
     * its contents for the lifetime of the container.
     */
    public function driver(?string $name = null): SettingsRepository;

    /**
     * The driver used when nothing is asked for by name.
     */
    public function getDefaultDriver(): string;

    /**
     * Forget the resolved instances, so the next driver() call rebuilds them
     * from the current configuration.
     */
    public function forgetDrivers(): void;
}
