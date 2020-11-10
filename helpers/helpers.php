<?php

use BonsaiCms\Settings\Contracts\SettingsManager;

if ( ! function_exists('settings')) {
    function settings($mixed = null, $value = null)
    {
        $settings = app(SettingsManager::class);

        if ($mixed === null) {
            return $settings;
        }

        if (is_array($mixed) || $value !== null) {
            return $settings->set($mixed, $value);
        }

        return $settings->get($mixed);
    }
}
