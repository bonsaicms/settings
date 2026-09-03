<?php

use Illuminate\Support\Facades\Artisan;
use BonsaiCms\Settings\Contracts\SettingsManager;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;
use BonsaiCms\Settings\Exceptions\UnsupportedDriverException;
use BonsaiCms\Settings\SettingsFacade as Settings;

/*
|--------------------------------------------------------------------------
| php artisan settings:delete-all
|--------------------------------------------------------------------------
|
| The command is registered only when the application runs in the console,
| which a test does, so it is reachable here exactly as it is from a terminal.
|
*/

beforeEach(function () {
    $this->factory = app(SettingsRepositoryFactory::class);
});

it('is registered with the application', function () {
    expect(array_keys(Artisan::all()))->toContain('settings:delete-all');
});

it('empties the default driver', function () {
    Settings::set(['a' => 'A', 'b' => 'B']);
    Settings::save();

    $this->artisan('settings:delete-all')
        ->expectsOutput('Settings were successfully deleted.')
        ->assertSuccessful();

    expect($this->factory->driver()->getAll())->toBe([]);
});

it('empties the manager cache along with the store', function () {
    /*
     * Without going through the manager, its in memory cache would still be
     * answering with settings that are no longer there - and the next save()
     * would write every one of them back.
     */
    Settings::set('a', 'A');
    Settings::save();
    expect(Settings::get('a'))->toBe('A');

    $this->artisan('settings:delete-all')->assertSuccessful();

    expect(Settings::get('a'))->toBeNull();
    expect(Settings::has('a'))->toBeFalse();
    expect(Settings::all()->toArray())->toBe([]);
});

it('says so even when there was nothing to delete', function () {
    $this->artisan('settings:delete-all')
        ->expectsOutput('Settings were successfully deleted.')
        ->assertSuccessful();
});

it('empties the named driver instead of the default one', function () {
    config()->set('settings.drivers.spare', ['driver' => 'array']);

    $this->factory->driver('spare')->setItem('a', 'A-ser');
    Settings::set('b', 'B');
    Settings::save();

    $this->artisan('settings:delete-all', ['--driver' => 'spare'])
        ->assertSuccessful();

    expect($this->factory->driver('spare')->getAll())->toBe([]);

    // The default driver is none of that driver's business
    expect($this->factory->driver()->getAll())->not->toBe([]);
    expect(Settings::get('b'))->toBe('B');
});

it('leaves the manager cache alone when emptying a named driver', function () {
    /*
     * The manager holds the default driver, so a command aimed at another one
     * must not throw its cache away - the settings it is holding are still
     * there.
     */
    config()->set('settings.drivers.spare', ['driver' => 'array']);

    Settings::set('a', 'A');
    Settings::save();

    $this->artisan('settings:delete-all', ['--driver' => 'spare'])
        ->assertSuccessful();

    expect(Settings::get('a'))->toBe('A');
});

it('refuses a driver that is not configured', function () {
    $this->artisan('settings:delete-all', ['--driver' => 'nope']);
})->throws(UnsupportedDriverException::class, 'Settings driver [nope] is not defined');

it('deletes for real, with nothing left for a later save to bring back', function () {
    Settings::set(['a' => 'A', 'b' => 'B']);
    Settings::save();

    $this->artisan('settings:delete-all')->assertSuccessful();

    Settings::save();
    app(SettingsManager::class)->refresh();

    expect(Settings::all()->toArray())->toBe([]);
});
