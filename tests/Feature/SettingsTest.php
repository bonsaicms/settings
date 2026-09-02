<?php

use Tests\Mocks\TestModel;
use Illuminate\Support\Facades\DB;
use BonsaiCms\Settings\SettingsFacade as Settings;
use BonsaiCms\Settings\Contracts\SettingsManager;

/**
 * Drops everything the manager holds in memory, so the next read has to go
 * back to the database.
 */
function forgetCachedSettings(): void
{
    app(SettingsManager::class)->refresh();
}

it('persists a setting and reads it back on a later request', function () {
    Settings::set('a', 'A');
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBe('A');
});

it('persists many settings and reads them back one by one', function () {
    Settings::set([
        'a' => 'A',
        'b' => 'B',
    ]);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBe('A');
    expect(Settings::get('b'))->toBe('B');
});

it('reads many settings back at once', function () {
    Settings::set([
        'a' => 'A',
        'b' => 'B',
    ]);
    Settings::save();

    forgetCachedSettings();

    // This is the path that broke: the repository fed whole models into the
    // deserializer, so a multi key read blew up against a real database
    expect(Settings::get(['a', 'b', 'missing'])->toArray())->toEqual([
        'a' => 'A',
        'b' => 'B',
        'missing' => null,
    ]);
});

it('round trips values of any type', function ($value) {
    Settings::set('a', $value);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toEqual($value);
})->with([
    'string' => ['test'],
    'integer' => [1],
    'float' => [1.5],
    'true' => [true],
    'false' => [false],
    'array' => [['a' => 'A', 'b' => 'B']],
    'object' => [fn () => (object) ['a' => 'A']],
]);

it('round trips an eloquent model by identity', function () {
    TestModel::createTable();
    $model = TestModel::create(['name' => 'original name']);

    Settings::set('model', $model);
    Settings::save();

    forgetCachedSettings();

    $retrieved = Settings::get('model');

    expect($retrieved)->toBeInstanceOf(TestModel::class);
    expect($retrieved->is($model))->toBeTrue();

    // The attributes are re-read from the database, never serialized
    $model->update(['name' => 'new name']);
    forgetCachedSettings();

    expect(Settings::get('model')->name)->toBe('new name');
});

it('reports whether a setting exists', function () {
    Settings::set('a', 'A');
    Settings::save();

    forgetCachedSettings();

    expect(Settings::has('a'))->toBeTrue();
    expect(Settings::has('missing'))->toBeFalse();
});

it('deletes a setting when it is set to null', function () {
    Settings::set('a', 'A');
    Settings::save();

    Settings::set('a', null);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBeNull();
    expect(Settings::has('a'))->toBeFalse();
    $this->assertDatabaseMissing(config('settings.database.table'), ['key' => 'a']);
});

it('deletes every setting', function () {
    Settings::set([
        'a' => 'A',
        'b' => 'B',
    ]);
    Settings::save();

    Settings::deleteAll();

    expect(Settings::has('a'))->toBeFalse();
    expect(Settings::get('a'))->toBeNull();

    forgetCachedSettings();

    expect(Settings::get(['a', 'b'])->toArray())->toEqual([
        'a' => null,
        'b' => null,
    ]);
});

it('does not query the database again once everything is loaded', function () {
    Settings::set([
        'a' => 'A',
        'b' => 'B',
    ]);
    Settings::save();

    forgetCachedSettings();

    Settings::all();

    DB::connection()->flushQueryLog();
    DB::connection()->enableQueryLog();

    expect(Settings::get('a'))->toBe('A');
    expect(Settings::get(['a', 'b'])->toArray())->toEqual(['a' => 'A', 'b' => 'B']);
    expect(Settings::has('a'))->toBeTrue();
    expect(Settings::get('missing'))->toBeNull();

    expect(DB::connection()->getQueryLog())->toBeEmpty();
});

it('exposes the same manager through the settings helper', function () {
    settings('a', 'A');
    settings(['b' => 'B']);
    settings()->save();

    forgetCachedSettings();

    expect(settings('a'))->toBe('A');
    expect(settings(['a', 'b'])->toArray())->toEqual(['a' => 'A', 'b' => 'B']);
    expect(settings()->has('a'))->toBeTrue();

    settings()->deleteAll();

    expect(settings()->has('a'))->toBeFalse();
});
