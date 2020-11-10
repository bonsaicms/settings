<?php

use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsManager;

if ( ! function_exists('settings')) {
    function settings(...$params)
    {
        $settings = app(SettingsManager::class);

        $paramsCount = count($params);

        if ($paramsCount === 0) {
            return $settings;
        }

        if ($paramsCount === 1) {
            if (is_array($params[0])) {
                $ar = new Collection($params[0]);
                if ($ar->keys()->join('') === $ar->values()->keys()->join('')) {
                    return $settings->get($params[0]);
                } else {
                    return $settings->set($params[0]);
                }
            } else {
                return $settings->get($params[0]);
            }
        }

        return $settings->set($params[0], $params[1]);
    }
}
