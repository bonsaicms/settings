<?php

use Tests\Mocks\TestModel;
use Tests\Mocks\ThrowingSettingsRepository;
use BonsaiCms\Settings\SettingsFacade as Settings;
use BonsaiCms\Settings\Contracts\SettingsManager;
use BonsaiCms\Settings\Contracts\SettingsRepository;

/*
 * Nothing in this file knows which driver it is running against: the suite is
 * pointed at one with SETTINGS_DRIVER, and CI runs it against every driver in
 * turn. Keep it that way - assertions about rows and tables belong in
 * SettingsRepositoryTest.
 */

/**
 * Drops everything the manager holds in memory, so the next read has to go
 * back to the repository.
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

    // Not merely absent from the cache - gone from the store underneath
    expect(app(SettingsRepository::class)->getAll())->not->toHaveKey('a');
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

it('does not go back to the repository once everything is loaded', function () {
    Settings::set([
        'a' => 'A',
        'b' => 'B',
    ]);
    Settings::save();

    forgetCachedSettings();

    Settings::all();

    /*
     * all() sets the loadedAll flag, after which a cache miss means the key
     * genuinely does not exist - so nothing below may reach the store. This is
     * what makes the LoadSettings middleware pay off.
     */
    app(SettingsManager::class)->setRepository(new ThrowingSettingsRepository);

    expect(Settings::get('a'))->toBe('A');
    expect(Settings::get(['a', 'b'])->toArray())->toEqual(['a' => 'A', 'b' => 'B']);
    expect(Settings::has('a'))->toBeTrue();
    expect(Settings::get('missing'))->toBeNull();
    expect(Settings::all()->toArray())->toEqual(['a' => 'A', 'b' => 'B']);
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
