<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bind Implementations
    |--------------------------------------------------------------------------
    */
    'implementations' => [
        BonsaiCms\Settings\Contracts\SettingsManager::class => BonsaiCms\Settings\SettingsManager::class,
        BonsaiCms\Settings\Contracts\SettingsSerializer::class => BonsaiCms\Settings\SettingsSerializer::class,
        BonsaiCms\Settings\Contracts\SettingsDeserializer::class => BonsaiCms\Settings\SettingsDeserializer::class,

        /*
        |--------------------------------------------------------------------------
        | Settings Repository Implementation
        |--------------------------------------------------------------------------
        |
        | Supported:
        |
        |   BonsaiCms\Settings\Repositories\DatabaseSettingsRepository::class
        |       - default
        |       - stores the settings in the database
        |
        |   BonsaiCms\Settings\Repositories\ArraySettingsRepository::class
        |       - for debugging purpose only
        |       - do not use this on production
        |       - it will NOT store any settings between two requests
        |
        */
        BonsaiCms\Settings\Contracts\SettingsRepository::class => BonsaiCms\Settings\Repositories\DatabaseSettingsRepository::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings for "DatabaseSettingsRepository" driver
    |--------------------------------------------------------------------------
    |
    */
    'database' => [
        'connection' => null,
        'table' => 'bonsaicms_settings',
        'model' => \BonsaiCms\Settings\Models\Setting::class,
    ],

    'throwExceptions' => [
        'serialize' => env('APP_DEBUG'),
        'deserialize' => env('APP_DEBUG'),
    ],

];
