<?php

namespace BonsaiCms\Settings\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use BonsaiCms\Settings\Contracts\SettingsManager;

/**
 * Loads every setting up front, so each get() further down the request is
 * answered from memory instead of from the store.
 */
class LoadSettings
{
    public function __construct(
        protected readonly SettingsManager $settingsManager
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->settingsManager->all();

        return $next($request);
    }
}
