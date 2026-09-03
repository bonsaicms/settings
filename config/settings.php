<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Settings Driver
    |--------------------------------------------------------------------------
    |
    | The driver used whenever a repository is resolved without a name. It has
    | to match one of the keys in the "drivers" array below.
    |
    */
    'default' => env('SETTINGS_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Settings Drivers
    |--------------------------------------------------------------------------
    |
    | Each entry is a driver you can resolve by name. The "driver" key picks
    | the implementation (see "driver_implementations"); everything else is
    | that implementation's own configuration.
    |
    | The names are yours: define two Redis drivers under different names to
    | keep two sets of settings apart. SETTINGS_DRIVER picks the active one,
    | and SettingsRepositoryFactory::driver($name) resolves any of them.
    |
    */
    'drivers' => [

        /*
        | Stores one row per setting. The default, and the only driver that
        | needs the published migration.
        */
        'database' => [
            'driver' => 'database',
            'connection' => env('SETTINGS_DATABASE_CONNECTION'),
            'table' => env('SETTINGS_DATABASE_TABLE', 'bonsaicms_settings'),
            'model' => BonsaiCms\Settings\Models\Setting::class,
        ],

        /*
        | Stores every setting as a field of one Redis hash. "connection" is a
        | connection from the application's config/database.php.
        */
        'redis' => [
            'driver' => 'redis',
            'connection' => env('SETTINGS_REDIS_CONNECTION', 'default'),
            'key' => env('SETTINGS_REDIS_KEY', 'bonsaicms_settings'),
        ],

        /*
        | Stores every setting in one JSON file. Meant for a single
        | application server; see the driver class for the locking caveat.
        */
        'file' => [
            'driver' => 'file',
            'path' => env('SETTINGS_FILE_PATH', storage_path('app/bonsaicms_settings.json')),
        ],

        /*
        | Keeps the settings in memory only. For debugging and tests - it will
        | NOT store anything between two requests.
        */
        'array' => [
            'driver' => 'array',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Implementations
    |--------------------------------------------------------------------------
    |
    | Maps a driver type to the class implementing it. Add an entry here to
    | teach the factory about a repository of your own, then refer to it from
    | a "drivers" entry by its type.
    |
    */
    'driver_implementations' => [
        'array' => BonsaiCms\Settings\Repositories\ArraySettingsRepository::class,
        'database' => BonsaiCms\Settings\Repositories\DatabaseSettingsRepository::class,
        'file' => BonsaiCms\Settings\Repositories\FileSettingsRepository::class,
        'redis' => BonsaiCms\Settings\Repositories\RedisSettingsRepository::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration
    |--------------------------------------------------------------------------
    |
    | The published migration creates the table for the database driver named
    | here, so it stays in step with that driver's "connection" and "table"
    | without repeating them.
    |
    */
    'migrations' => [
        'driver' => env('SETTINGS_MIGRATION_DRIVER', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bind Implementations
    |--------------------------------------------------------------------------
    |
    | Every seam in the package is an interface bound from this array at
    | register() time, so replacing any piece is a one line change.
    |
    */
    'implementations' => [
        BonsaiCms\Settings\Contracts\SettingsManager::class => BonsaiCms\Settings\SettingsManager::class,
        BonsaiCms\Settings\Contracts\SettingsSerializer::class => BonsaiCms\Settings\SettingsSerializer::class,
        BonsaiCms\Settings\Contracts\SettingsDeserializer::class => BonsaiCms\Settings\SettingsDeserializer::class,
        BonsaiCms\Settings\Contracts\SettingsRepositoryFactory::class => BonsaiCms\Settings\SettingsRepositoryFactory::class,
    ],

    'throwExceptions' => [
        'serialize' => env('APP_DEBUG'),
        'deserialize' => env('APP_DEBUG'),
    ],

];
