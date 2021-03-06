<?php

namespace BonsaiCms\Settings\Http\Middleware;

use Closure;
use BonsaiCms\Settings\Contracts\SettingsManager;

class LoadSettings
{
    protected $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function handle($request, Closure $next)
    {
        $this->settingsManager->all();

        return $next($request);
    }
}
