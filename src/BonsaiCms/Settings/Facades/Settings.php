<?php

namespace BonsaiCms\Settings\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use BonsaiCms\Settings\Contracts\SettingsManager;

/**
 * @method static mixed get(string|array<int, string>|Collection<int, string> $keyOrKeys)
 * @method static void set(string|array<string, mixed>|Collection<string, mixed> $keyOrPairs, mixed $value = null)
 * @method static bool has(string $key)
 * @method static Collection<string, mixed> all()
 * @method static void save()
 * @method static void deleteAll()
 * @method static void refresh()
 * @method static bool isDirty()
 *
 * @see \BonsaiCms\Settings\Contracts\SettingsManager
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsManager::class;
    }
}
