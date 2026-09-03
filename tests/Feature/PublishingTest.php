<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use BonsaiCms\Settings\SettingsServiceProvider;

/**
 * The package does not register its migration with the application, so
 * publishing is the only way a host app gets the settings table. If these
 * groups are renamed or dropped, installing the package silently stops
 * creating the table.
 */

/**
 * The two tests below publish into the application testbench simulates, which
 * is a real directory inside vendor/. Cleaning up here and not at the end of
 * the test means a failing one cannot leave a stray migration behind for
 * every later run to pick up.
 */
afterEach(function () {
    File::delete(config_path('settings.php'));
    File::delete(File::glob(database_path('migrations').'/*bonsaicms_create_settings_table.php'));
});

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

it('actually writes the config file into the application', function () {
    /*
     * The tags above are only half of it: the destination has to be right too,
     * and vendor:publish is what an application really runs.
     */
    $destination = config_path('settings.php');

    $this->artisan('vendor:publish', ['--tag' => 'settings-config'])->assertSuccessful();

    expect(File::exists($destination))->toBeTrue();
    expect(require $destination)->toHaveKeys(['default', 'drivers', 'driver_implementations']);
});

it('actually writes the migration into the application, with a fresh timestamp', function () {
    $this->artisan('vendor:publish', ['--tag' => 'settings-migrations'])->assertSuccessful();

    $published = File::glob(database_path('migrations').'/*bonsaicms_create_settings_table.php');

    expect($published)->toHaveCount(1);

    /*
     * Published with the timestamp of the moment rather than the year 2000
     * placeholder, so it runs after the application's own migrations.
     */
    expect(basename($published[0]))->not->toStartWith('2000_01_01');
});
