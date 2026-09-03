<?php

use Tests\Mocks\TestModel;
use Tests\Mocks\TestWrappableClass;
use Tests\Mocks\ThrowingSettingsRepository;
use BonsaiCms\Settings\SettingsFacade as Settings;
use BonsaiCms\Settings\SettingsManager;
use BonsaiCms\Settings\Contracts;

/*
|--------------------------------------------------------------------------
| The package end to end, through a real driver
|--------------------------------------------------------------------------
|
| Nothing in this file knows which driver it is running against: the suite is
| pointed at one with SETTINGS_DRIVER, and CI runs it against every driver in
| turn. Keep it that way - assertions about rows, tables, hashes or files
| belong in the driver's own test file.
|
| The mocked unit tests cannot see a repository handing back the wrong shape,
| which is exactly how getItems() once shipped broken, so anything about
| persistence belongs here.
|
*/

/**
 * Drops everything the manager holds in memory, so the next read has to go
 * back to the repository - the same state the next request would start in.
 */
function forgetCachedSettings(): void
{
    app(Contracts\SettingsManager::class)->refresh();
}

/**
 * A second manager over the same store, as another process would see it.
 */
function anotherManager(): Contracts\SettingsManager
{
    return new SettingsManager(
        app(Contracts\SettingsRepository::class),
        app(Contracts\SettingsSerializer::class),
        app(Contracts\SettingsDeserializer::class)
    );
}

/*
|--------------------------------------------------------------------------
| Persisting
|--------------------------------------------------------------------------
*/

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

it('reads many settings back in the order they were asked for', function () {
    Settings::set(['a' => 'A', 'b' => 'B']);
    Settings::save();

    forgetCachedSettings();

    expect(array_keys(Settings::get(['b', 'a'])->toArray()))->toBe(['b', 'a']);
});

it('reads everything back at once', function () {
    Settings::set(['a' => 'A', 'b' => 'B']);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::all()->toArray())->toEqual(['a' => 'A', 'b' => 'B']);
});

it('overwrites a setting that was already saved', function () {
    Settings::set('a', 'A');
    Settings::save();

    Settings::set('a', 'A2');
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBe('A2');
    expect(Settings::all()->toArray())->toEqual(['a' => 'A2']);
});

it('shows a saved setting to everything else looking at the same store', function () {
    Settings::set('a', 'A');
    Settings::save();

    expect(anotherManager()->get('a'))->toBe('A');
});

it('shows nothing to anything else until save is called', function () {
    // Gotcha number one: set() writes to memory, save() writes to the store
    Settings::set('a', 'A');

    expect(Settings::get('a'))->toBe('A');
    expect(anotherManager()->get('a'))->toBeNull();
});

it('loses unsaved changes on a refresh', function () {
    Settings::set('a', 'A');

    forgetCachedSettings();

    expect(Settings::get('a'))->toBeNull();
    expect(Settings::isDirty())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| What can be stored
|--------------------------------------------------------------------------
*/

it('round trips primitives', function ($value) {
    Settings::set('a', $value);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBe($value);
})->with('primitives');

it('round trips objects', function ($value) {
    Settings::set('a', $value);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toEqual($value);
})->with('objects');

it('round trips a value that is not plain ascii', function () {
    $value = "ľšč \" ' \\ {} \n done";

    Settings::set('a', $value);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBe($value);
});

it('round trips a value far too big for a varchar', function () {
    $value = array_fill(0, 500, 'a fairly long string of no importance');

    Settings::set('a', $value);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBe($value);
});

it('round trips an object that says how to rebuild itself', function () {
    Settings::set('a', new TestWrappableClass('some-secret'));
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBeInstanceOf(TestWrappableClass::class);
    expect(Settings::get('a')->getSecret())
        ->toBe('some-secret-was-unwrapped-'.TestWrappableClass::class);
});

it('stores a setting under a namespaced key', function () {
    Settings::set(['mail.from.address' => 'a@example.com', 'tenant:17:theme' => 'dark']);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('mail.from.address'))->toBe('a@example.com');
    expect(Settings::get(['tenant:17:theme'])->toArray())->toBe(['tenant:17:theme' => 'dark']);
});

/*
|--------------------------------------------------------------------------
| Eloquent models
|--------------------------------------------------------------------------
*/

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

it('reads a setting whose model has since been deleted as absent', function () {
    /*
     * The trait stores only the primary key, so a deleted row leaves the
     * setting pointing at nothing. find() answers null, and null means absent
     * everywhere in this package - so has() has to agree.
     */
    TestModel::createTable();
    $model = TestModel::create(['name' => 'original name']);

    Settings::set('model', $model);
    Settings::save();

    $model->delete();
    forgetCachedSettings();

    expect(Settings::get('model'))->toBeNull();
    expect(Settings::has('model'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Absence
|--------------------------------------------------------------------------
*/

it('reports whether a setting exists', function () {
    Settings::set('a', 'A');
    Settings::save();

    forgetCachedSettings();

    expect(Settings::has('a'))->toBeTrue();
    expect(Settings::has('missing'))->toBeFalse();
});

it('reports a falsy setting as existing, because only null means absent', function ($value) {
    Settings::set('a', $value);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::has('a'))->toBeTrue();
    expect(Settings::get('a'))->toBe($value);
})->with([
    'false' => [false],
    'zero' => [0],
    'empty string' => [''],
]);

it('deletes a setting when it is set to null', function () {
    Settings::set('a', 'A');
    Settings::save();

    Settings::set('a', null);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::get('a'))->toBeNull();
    expect(Settings::has('a'))->toBeFalse();

    // Not merely absent from the cache - gone from the store underneath
    expect(app(Contracts\SettingsRepository::class)->getAll())->not->toHaveKey('a');
});

it('deletes one setting without disturbing the others', function () {
    Settings::set(['a' => 'A', 'b' => 'B']);
    Settings::save();

    Settings::set('a', null);
    Settings::save();

    forgetCachedSettings();

    expect(Settings::all()->toArray())->toEqual(['b' => 'B']);
});

it('leaves a key that does not exist out of everything', function () {
    Settings::set('a', 'A');
    Settings::save();

    forgetCachedSettings();

    // Reading a missing key must not make it look like a setting afterwards
    expect(Settings::get('missing'))->toBeNull();
    expect(Settings::get(['also-missing'])->toArray())->toBe(['also-missing' => null]);

    expect(Settings::all()->toArray())->toEqual(['a' => 'A']);
    expect(Settings::all())->not->toHaveKey('missing');

    Settings::save();
    forgetCachedSettings();

    expect(Settings::all()->toArray())->toEqual(['a' => 'A']);
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
    expect(Settings::all()->toArray())->toBe([]);
});

it('deletes every setting without waiting for a save', function () {
    Settings::set('a', 'A');
    Settings::save();

    Settings::deleteAll();

    // Gotcha number two: deleteAll() hits the store there and then
    expect(anotherManager()->get('a'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The in-memory cache
|--------------------------------------------------------------------------
*/

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
    app(Contracts\SettingsManager::class)->setRepository(new ThrowingSettingsRepository);

    expect(Settings::get('a'))->toBe('A');
    expect(Settings::get(['a', 'b'])->toArray())->toEqual(['a' => 'A', 'b' => 'B']);
    expect(Settings::has('a'))->toBeTrue();
    expect(Settings::get('missing'))->toBeNull();
    expect(Settings::all()->toArray())->toEqual(['a' => 'A', 'b' => 'B']);
});

it('rewrites every setting on a save, not only the ones that changed', function () {
    /*
     * Gotcha number five, and the reason updated_at moves on rows nobody
     * touched: save() writes the whole cache back.
     */
    Settings::set(['a' => 'A', 'b' => 'B']);
    Settings::save();

    forgetCachedSettings();
    Settings::all();
    Settings::set('b', 'B2');
    Settings::save();

    forgetCachedSettings();

    expect(Settings::all()->toArray())->toEqual(['a' => 'A', 'b' => 'B2']);
});

/*
|--------------------------------------------------------------------------
| The helper
|--------------------------------------------------------------------------
*/

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

it('reaches the same settings through the facade, the helper and the container', function () {
    Settings::set('a', 'A');

    expect(settings('a'))->toBe('A');
    expect(app(Contracts\SettingsManager::class)->get('a'))->toBe('A');

    // The short alias an application gets without importing anything
    expect(\Settings::get('a'))->toBe('A');
});
