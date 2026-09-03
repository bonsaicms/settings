<?php

use Tests\FeatureTestCase;
use Illuminate\Support\Facades\Schema;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;
use BonsaiCms\Settings\Models\Setting;
use BonsaiCms\Settings\Repositories\DatabaseSettingsRepository;

/*
 * Every implementation of the SettingsRepository contract has to behave the
 * same way, so every test below runs against each of them. This is the only
 * place that keeps the drivers from drifting apart, so a new driver belongs
 * in this dataset before anything else.
 *
 * The drivers that keep their contents outside the PHP process are emptied on
 * the way in, because unlike the database they are not rolled back between
 * tests.
 */
function repository(string $driver)
{
    $repository = app(SettingsRepositoryFactory::class)->driver($driver);

    $repository->deleteAll();

    return $repository;
}

dataset('repositories', [
    'database' => [fn () => repository('database')],
    'array' => [fn () => repository('array')],
    'file' => [fn () => repository('file')],
    'redis' => [function () {
        FeatureTestCase::requireRedis();

        return repository('redis');
    }],
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

it('returns nothing when asked for no keys', function ($repository) {
    $repository->setItem('a', 'A-ser');

    expect($repository->getItems([]))->toBe([]);
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

it('stores a value that is not plain ascii', function ($repository) {
    /*
     * Serialized values are base64, but nothing stops an application from
     * binding a different serializer, so no driver may mangle the string it
     * is handed - not the JSON encoding of the file driver, and not the
     * quoting of any database.
     */
    $value = "\u{013E}\u{0161}\u{010D} \" ' \\ {} \n done";

    $repository->setItem('a', $value);

    expect($repository->getItem('a'))->toBe($value);
    expect($repository->getAll())->toEqual(['a' => $value]);
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

it('can be emptied when it is already empty', function ($repository) {
    $repository->deleteAll();

    expect($repository->getAll())->toEqual([]);
})->with('repositories');

/*
|--------------------------------------------------------------------------
| Database driver specifics
|--------------------------------------------------------------------------
*/

it('leaves no rows behind after deleting everything', function () {
    $repository = repository('database');

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
    config()->set('settings.drivers.database.connection', 'settings_only');

    // The migration has to create the table on the configured connection
    $migration = require __DIR__.'/../../database/migrations/2000_01_01_000000_bonsaicms_create_settings_table.php';
    $migration->up();

    $repository = new DatabaseSettingsRepository(config('settings.drivers.database'));
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect($repository->getItems(['a', 'b']))->toEqual([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $repository->deleteAll();

    expect($repository->getAll())->toEqual([]);
});

it('stores the value in the configured table', function () {
    repository('database')->setItem('a', 'A-ser');

    $this->assertDatabaseHas(config('settings.drivers.database.table'), [
        'key' => 'a',
        'value' => 'A-ser',
    ]);
});

it('keeps two database drivers on two tables apart', function () {
    Schema::create('other_settings', function ($table) {
        $table->string('key')->primary();
        $table->text('value');
        $table->timestamps();
    });

    config()->set('settings.drivers.other', [
        'driver' => 'database',
        'table' => 'other_settings',
        'model' => Setting::class,
    ]);

    $default = repository('database');

    repository('other')->setItem('a', 'A-ser');

    $this->assertDatabaseHas('other_settings', ['key' => 'a']);
    $this->assertDatabaseMissing(config('settings.drivers.database.table'), ['key' => 'a']);

    // The two drivers share a model class but must not share its table
    expect($default->getItem('a'))->toBeNull();
});
