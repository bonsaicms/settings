<?php

namespace BonsaiCms\Settings;

use Illuminate\Support\Facades\Facade;
use BonsaiCms\Settings\Contracts\SettingsManager;

class SettingsFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return SettingsManager::class;
    }
}
