<?php

use BonsaiCms\Settings\Models\Setting;
use BonsaiCms\Settings\Repositories\ArraySettingsRepository;
use BonsaiCms\Settings\Repositories\DatabaseSettingsRepository;

/*
 * Both implementations of the SettingsRepository contract have to behave the
 * same way, so every test below runs against each of them.
 */
dataset('repositories', [
    'database' => [fn () => new DatabaseSettingsRepository],
    'array' => [fn () => new ArraySettingsRepository],
]);

it('stores and reads back one item', function ($repository) {
    $repository->setItem('a', 'A-ser');

    expect($repository->getItem('a'))->toBe('A-ser');
})->with('repositories');

it('returns null for a missing item', function ($repository) {
    expect($repository->getItem('missing'))->toBeNull();
})->with('repositories');

it('reads back the stored values and not the underlying records', function ($repository) {
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $items = $repository->getItems(['a', 'b']);

    expect($items)->toEqual([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    // The regression: getItems() used to hand back whole Eloquent models
    expect($items['a'])->toBeString();
})->with('repositories');

it('keys missing items as null in getItems', function ($repository) {
    $repository->setItem('a', 'A-ser');

    expect($repository->getItems(['a', 'missing']))->toEqual([
        'a' => 'A-ser',
        'missing' => null,
    ]);
})->with('repositories');

it('preserves the requested order in getItems', function ($repository) {
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect(array_keys($repository->getItems(['b', 'a'])))->toBe(['b', 'a']);
})->with('repositories');

it('deletes an item when a null value is set', function ($repository) {
    $repository->setItem('a', 'A-ser');
    $repository->setItem('a', null);

    expect($repository->getItem('a'))->toBeNull();
    expect($repository->getAll())->toEqual([]);
})->with('repositories');

it('deletes items with a null value when setting many', function ($repository) {
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $repository->setItems([
        'a' => null,
        'c' => 'C-ser',
    ]);

    expect($repository->getAll())->toEqual([
        'b' => 'B-ser',
        'c' => 'C-ser',
    ]);
})->with('repositories');

it('overwrites an existing item when setting many', function ($repository) {
    $repository->setItem('a', 'A-ser');

    $repository->setItems(['a' => 'A2-ser']);

    expect($repository->getItem('a'))->toBe('A2-ser');
})->with('repositories');

it('returns every item from getAll', function ($repository) {
    expect($repository->getAll())->toEqual([]);

    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect($repository->getAll())->toEqual([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);
})->with('repositories');

it('deletes everything', function ($repository) {
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $repository->deleteAll();

    expect($repository->getAll())->toEqual([]);
    expect($repository->getItem('a'))->toBeNull();
})->with('repositories');

it('leaves no rows behind after deleting everything', function () {
    $repository = new DatabaseSettingsRepository;

    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect(Setting::count())->toBe(2);

    // deleteAll() used to call truncate(), which blows up on SQLite
    $repository->deleteAll();

    expect(Setting::count())->toBe(0);
});

it('works on a dedicated connection of its own', function () {
    config()->set('database.connections.settings_only', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('settings.database.connection', 'settings_only');

    // The migration has to create the table on the configured connection
    $migration = require __DIR__.'/../../database/migrations/2000_01_01_000000_bonsaicms_create_settings_table.php';
    $migration->up();

    $repository = new DatabaseSettingsRepository;
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect($repository->getItems(['a', 'b']))->toEqual([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    /*
     * Nothing on this connection uses AUTOINCREMENT, so there is no
     * "sqlite_sequence" table for a truncate() to clean up.
     */
    $repository->deleteAll();

    expect($repository->getAll())->toEqual([]);
});

it('stores the value in the configured table', function () {
    (new DatabaseSettingsRepository)->setItem('a', 'A-ser');

    $this->assertDatabaseHas(config('settings.database.table'), [
        'key' => 'a',
        'value' => 'A-ser',
    ]);
});
