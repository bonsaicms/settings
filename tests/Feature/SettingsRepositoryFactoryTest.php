<?php

use Tests\FeatureTestCase;
use BonsaiCms\Settings\Contracts\SettingsRepository;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;
use BonsaiCms\Settings\Exceptions\UnsupportedDriverException;
use BonsaiCms\Settings\Repositories\ArraySettingsRepository;
use BonsaiCms\Settings\Repositories\DatabaseSettingsRepository;
use BonsaiCms\Settings\Repositories\FileSettingsRepository;
use BonsaiCms\Settings\Repositories\RedisSettingsRepository;

/**
 * The factory is what turns "settings.drivers" into repositories, so it is
 * the seam that has to hold for a driver to be selectable at all.
 */

function factory(): SettingsRepositoryFactory
{
    return app(SettingsRepositoryFactory::class);
}

it('resolves every driver shipped with the package', function (string $name, string $class) {
    expect(factory()->driver($name))->toBeInstanceOf($class);
})->with([
    'database' => ['database', DatabaseSettingsRepository::class],
    'array' => ['array', ArraySettingsRepository::class],
    'file' => ['file', FileSettingsRepository::class],
    'redis' => ['redis', RedisSettingsRepository::class],
]);

it('resolves every driver the shipped config declares', function () {
    /*
     * The hard coded list above says which class each driver must be; this one
     * says none of them may be forgotten. A driver added to the config file
     * without an entry in "driver_implementations" - or with a typo in it -
     * fails here rather than the first time an application selects it.
     */
    foreach (array_keys(config('settings.drivers')) as $name) {
        if ($name === 'redis' && ! FeatureTestCase::redisIsAvailable()) {
            continue;
        }

        expect(factory()->driver($name))
            ->toBeInstanceOf(SettingsRepository::class)
            ->and(config('settings.drivers.'.$name.'.driver'))
            ->toBeIn(array_keys(config('settings.driver_implementations')));
    }
});

it('resolves the driver named by the default config when none is asked for', function () {
    config()->set('settings.default', 'array');

    expect(factory()->driver())->toBeInstanceOf(ArraySettingsRepository::class);
});

it('treats the default driver asked for by name as the same driver', function () {
    config()->set('settings.default', 'array');

    expect(factory()->driver())->toBe(factory()->driver('array'));
});

it('falls back to the database driver when the config names no default', function ($default) {
    /*
     * An application that publishes the config file and writes
     * env('SETTINGS_DRIVER') without a fallback of its own ends up here, and
     * so does one whose .env has the variable but leaves it empty.
     */
    config()->set('settings.default', $default);

    expect(factory()->getDefaultDriver())->toBe('database');
    expect(factory()->driver())->toBeInstanceOf(DatabaseSettingsRepository::class);
})->with([
    'null' => [null],
    'an empty string' => [''],
]);

it('binds the repository contract to the default driver', function () {
    config()->set('settings.default', 'array');

    expect(app(SettingsRepository::class))->toBeInstanceOf(ArraySettingsRepository::class);
});

/**
 * Reads the shipped config file with the given environment in place, which is
 * the only way to see the env() calls in it actually resolve - by the time a
 * test runs, the config has long been merged.
 */
function configWithEnvironment(array $environment): array
{
    $restore = [];

    foreach ($environment as $name => $value) {
        $restore[$name] = getenv($name);

        // A null value means "make sure this one is absent"
        if ($value === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        } else {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    try {
        return require __DIR__.'/../../config/settings.php';
    } finally {
        foreach ($restore as $name => $value) {
            if ($value === false) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

it('takes the default driver from the SETTINGS_DRIVER environment variable', function () {
    expect(configWithEnvironment(['SETTINGS_DRIVER' => 'file'])['default'])->toBe('file');
});

it('defaults to the database driver when SETTINGS_DRIVER is not set', function () {
    expect(configWithEnvironment(['SETTINGS_DRIVER' => null])['default'])->toBe('database');
});

it('configures each driver from its own environment variables', function () {
    $config = configWithEnvironment([
        'SETTINGS_DATABASE_CONNECTION' => 'some_connection',
        'SETTINGS_DATABASE_TABLE' => 'some_table',
        'SETTINGS_REDIS_CONNECTION' => 'some_redis',
        'SETTINGS_REDIS_KEY' => 'some_key',
        'SETTINGS_FILE_PATH' => '/tmp/some_file.json',
        'SETTINGS_MIGRATION_DRIVER' => 'some_driver',
    ]);

    expect($config['drivers']['database']['connection'])->toBe('some_connection');
    expect($config['drivers']['database']['table'])->toBe('some_table');
    expect($config['drivers']['redis']['connection'])->toBe('some_redis');
    expect($config['drivers']['redis']['key'])->toBe('some_key');
    expect($config['drivers']['file']['path'])->toBe('/tmp/some_file.json');
    expect($config['migrations']['driver'])->toBe('some_driver');
});

it('returns the same instance for the same driver name', function () {
    expect(factory()->driver('array'))->toBe(factory()->driver('array'));
});

it('keeps two drivers of the same type apart', function () {
    config()->set('settings.drivers.first', ['driver' => 'array']);
    config()->set('settings.drivers.second', ['driver' => 'array']);

    factory()->driver('first')->setItem('a', 'A-ser');

    expect(factory()->driver('first')->getItem('a'))->toBe('A-ser');
    expect(factory()->driver('second')->getItem('a'))->toBeNull();
});

it('lets an application name two redis drivers of its own', function () {
    FeatureTestCase::requireRedis();

    config()->set('settings.drivers.tenant_one', [
        'driver' => 'redis',
        'connection' => 'default',
        'key' => 'settings_tenant_one',
    ]);
    config()->set('settings.drivers.tenant_two', [
        'driver' => 'redis',
        'connection' => 'default',
        'key' => 'settings_tenant_two',
    ]);

    $one = factory()->driver('tenant_one');
    $two = factory()->driver('tenant_two');

    $one->deleteAll();
    $two->deleteAll();

    $one->setItem('a', 'A-ser');
    $two->setItem('a', 'B-ser');

    // Same type, same connection, different hash - so different contents
    expect($one->getItem('a'))->toBe('A-ser');
    expect($two->getItem('a'))->toBe('B-ser');
    expect($one->getAll())->toEqual(['a' => 'A-ser']);

    $one->deleteAll();

    expect($two->getAll())->toEqual(['a' => 'B-ser']);

    $two->deleteAll();
});

it('rebuilds the drivers after they are forgotten', function () {
    $first = factory()->driver('array');

    factory()->forgetDrivers();

    expect(factory()->driver('array'))->not->toBe($first);
});

it('refuses a driver that is not configured', function () {
    factory()->driver('nope');
})->throws(UnsupportedDriverException::class, 'Settings driver [nope] is not defined');

it('refuses a driver that declares no type', function () {
    config()->set('settings.drivers.broken', ['key' => 'whatever']);

    factory()->driver('broken');
})->throws(UnsupportedDriverException::class, 'does not declare a "driver" type');

it('refuses a driver type that has no implementation', function () {
    config()->set('settings.drivers.broken', ['driver' => 'carrier_pigeon']);

    factory()->driver('broken');
})->throws(UnsupportedDriverException::class, 'uses type [carrier_pigeon]');

it('resolves a driver implementation bound by the application', function () {
    config()->set('settings.driver_implementations.custom', ArraySettingsRepository::class);
    config()->set('settings.drivers.mine', ['driver' => 'custom']);

    expect(factory()->driver('mine'))->toBeInstanceOf(ArraySettingsRepository::class);
});
