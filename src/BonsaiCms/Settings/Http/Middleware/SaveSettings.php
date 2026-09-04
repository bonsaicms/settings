<?php

namespace BonsaiCms\Settings\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use BonsaiCms\Settings\Contracts\SettingsManager;

/**
 * Writes whatever the request changed back to the store once it is done, so
 * nothing in the application has to remember to call save().
 */
class SaveSettings
{
    public function __construct(
        protected readonly SettingsManager $settingsManager
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->settingsManager->isDirty()) {
            $this->settingsManager->save();
        }

        return $response;
    }
}
