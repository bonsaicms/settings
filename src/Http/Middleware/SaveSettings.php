<?php

namespace BonsaiCms\Settings\Http\Middleware;

use Closure;
use BonsaiCms\Settings\Contracts\SettingsManager;

class SaveSettings
{
    protected $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $this->settingsManager->save();

        return $response;
    }
}
