<?php

namespace Tests;

use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;
use BonsaiCms\Settings\SettingsFacade;
use BonsaiCms\Settings\SettingsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;

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

    /**
     * The package does not register its migration with the application; it is
     * published into the host app instead, so tests have to load it themselves.
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', static::databaseConnectionConfig());

        /*
         * Both clients are worth exercising: Laravel papers over a real
         * difference between them in HMGET, which is what getItems() rides on.
         */
        $app['config']->set('database.redis.client', env('REDIS_CLIENT', 'predis'));
        $app['config']->set('database.redis.options', [
            'prefix' => 'bonsaicms_settings_tests:',
        ]);
        $app['config']->set('database.redis.default', [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', 6379),
            'database' => (int) env('REDIS_DB', 0),
        ]);
    }

    /**
     * The package config is merged by the service provider, and mergeConfigFrom
     * only merges the top level - so anything nested has to be set after the
     * application has booted, not in defineEnvironment(), or it would replace
     * the whole "drivers" array instead of one value inside it.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('settings.default', static::settingsDriver());
        config()->set('settings.drivers.file.path', static::settingsFilePath());

        /*
         * Redis and the file keep their contents outside the PHP process, so
         * unlike the database they survive from one test to the next. Start
         * every test from an empty store.
         */
        $this->app->make(SettingsRepositoryFactory::class)->driver()->deleteAll();
    }

    /**
     * The driver the whole suite runs against. Every test in tests/Feature is
     * driver agnostic, so pointing SETTINGS_DRIVER at "redis" or "file" runs
     * the same assertions against that backend - which is what the CI matrix
     * does.
     */
    public static function settingsDriver(): string
    {
        return env('SETTINGS_DRIVER', 'database');
    }

    /**
     * A directory of this process's own, so a test run never touches a real
     * application's settings file and parallel workers cannot collide - tests
     * that delete the directory outright included.
     */
    public static function settingsFilePath(): string
    {
        return sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'bonsaicms-settings-tests-'.getmypid()
            .DIRECTORY_SEPARATOR.'settings.json';
    }

    /**
     * Skip when there is no Redis to talk to, so a plain "composer test" keeps
     * working on a machine without one. CI sets SETTINGS_REQUIRE_REDIS, which
     * turns the skip into a failure - a silently skipped job would defeat the
     * point of having a Redis service in the matrix.
     */
    /**
     * Whether there is a Redis to talk to, for a test that covers more than
     * Redis and only wants to leave that one driver out.
     */
    public static function redisIsAvailable(): bool
    {
        return static::redisIsReachable();
    }

    public static function requireRedis(): void
    {
        if (static::redisIsReachable()) {
            return;
        }

        $where = env('REDIS_HOST', '127.0.0.1').':'.env('REDIS_PORT', 6379);

        if (env('SETTINGS_REQUIRE_REDIS')) {
            Assert::fail("SETTINGS_REQUIRE_REDIS is set but no Redis is listening on {$where}.");
        }

        Assert::markTestSkipped("No Redis listening on {$where}; set one up to run this test.");
    }

    /**
     * Answered once per process: without a Redis to connect to this costs a
     * full connection timeout, and every skipped test would pay it again.
     *
     * @var bool|null
     */
    protected static $redisIsReachable = null;

    protected static function redisIsReachable(): bool
    {
        if (static::$redisIsReachable !== null) {
            return static::$redisIsReachable;
        }

        $socket = @fsockopen(
            env('REDIS_HOST', '127.0.0.1'),
            (int) env('REDIS_PORT', 6379),
            $errno,
            $errstr,
            1
        );

        if ($socket !== false) {
            fclose($socket);
        }

        return static::$redisIsReachable = ($socket !== false);
    }

    /**
     * The suite runs against sqlite ":memory:" by default, which needs nothing
     * installed. Point DB_DRIVER (plus the usual DB_HOST / DB_PORT /
     * DB_DATABASE / DB_USERNAME / DB_PASSWORD variables) at a real PostgreSQL
     * or MariaDB server to run the very same tests there — that is what the
     * "PostgreSQL" and "MariaDB" CI jobs do, so the SQL Eloquent generates for
     * this package (upserts above all) is verified on every engine.
     */
    public static function databaseConnectionConfig(): array
    {
        $driver = env('DB_DRIVER', 'sqlite');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => env('DB_DATABASE', ':memory:'),
                'prefix' => '',
            ];
        }

        if (! in_array($driver, ['pgsql', 'mysql', 'mariadb'], true)) {
            throw new InvalidArgumentException(
                "Unsupported DB_DRIVER [{$driver}]; expected sqlite, pgsql, mysql or mariadb."
            );
        }

        $postgres = ($driver === 'pgsql');

        return array_filter([
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $postgres ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'settings_test'),
            'username' => env('DB_USERNAME', $postgres ? 'postgres' : 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => $postgres ? 'utf8' : 'utf8mb4',
            'search_path' => $postgres ? 'public' : null,
            'prefix' => '',
        ], fn ($value) => $value !== null);
    }
}
