<?php

use BonsaiCms\Settings\Contracts\SettingsManager;

if (! function_exists('settings')) {
    /**
     * Reach the settings manager, or read and write through it in one call.
     *
     * The overload is on the shape of the arguments:
     *
     *   settings()                      the manager itself
     *   settings('key')                 read one
     *   settings(['a', 'b'])            read many - a list of keys
     *   settings(['a' => 1, 'b' => 2])  write many - a map of pairs
     *   settings('key', $value)         write one
     */
    function settings(mixed ...$params): mixed
    {
        $settings = app(SettingsManager::class);

        if ($params === []) {
            return $settings;
        }

        if (count($params) > 1) {
            $settings->set($params[0], $params[1]);

            return null;
        }

        /*
         * A list is a multi-get, anything else with keys is a multi-set. PHP
         * casts a numeric string key to an integer, so ['0' => 'theme'] is
         * indistinguishable from ['theme'] and is read rather than written;
         * write such a key through set() instead.
         */
        if (is_array($params[0]) && ! array_is_list($params[0])) {
            $settings->set($params[0]);

            return null;
        }

        return $settings->get($params[0]);
    }
}
