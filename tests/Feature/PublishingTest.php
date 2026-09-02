<?php

use BonsaiCms\Settings\SettingsServiceProvider;
use Illuminate\Support\ServiceProvider;

/**
 * The package does not register its migration with the application, so
 * publishing is the only way a host app gets the settings table. If these
 * groups are renamed or dropped, installing the package silently stops
 * creating the table.
 */

function publishedPaths(?string $group): array
{
    return array_map('realpath', array_keys(
        ServiceProvider::pathsToPublish(SettingsServiceProvider::class, $group)
    ));
}

it('publishes the migration under the settings-migrations tag', function () {
    expect(publishedPaths('settings-migrations'))
        ->toBe([realpath(__DIR__.'/../../database/migrations')]);
});

it('publishes the config under the settings-config tag', function () {
    expect(publishedPaths('settings-config'))
        ->toBe([realpath(__DIR__.'/../../config/settings.php')]);
});

it('publishes both under the settings tag', function () {
    expect(publishedPaths('settings'))->toHaveCount(2)
        ->toContain(realpath(__DIR__.'/../../config/settings.php'))
        ->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('registers the migration as a migration path so publishing restamps it', function () {
    expect(array_map('realpath', ServiceProvider::publishableMigrationPaths()))
        ->toContain(realpath(__DIR__.'/../../database/migrations'));
});
