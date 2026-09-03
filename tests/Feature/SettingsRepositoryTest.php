<?php

use Tests\FeatureTestCase;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;

/*
|--------------------------------------------------------------------------
| The SettingsRepository contract, against every implementation
|--------------------------------------------------------------------------
|
| Every implementation has to behave the same way, so every test below runs
| against each of them. This is the only thing keeping the drivers from
| drifting apart, so a new driver belongs in this dataset before anything
| else - and a new expectation belongs here rather than in one driver's own
| file, unless only that driver can get it wrong.
|
| The drivers that keep their contents outside the PHP process are emptied on
| the way in, because unlike the database they are not rolled back between
| tests.
|
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

/*
|--------------------------------------------------------------------------
| Reading and writing one key
|--------------------------------------------------------------------------
*/

it('stores and reads back one item', function ($repository) {
    $repository->setItem('a', 'A-ser');

    expect($repository->getItem('a'))->toBe('A-ser');
})->with('repositories');

it('returns null for a missing item', function ($repository) {
    expect($repository->getItem('missing'))->toBeNull();
})->with('repositories');

it('overwrites an item that is already there', function ($repository) {
    $repository->setItem('a', 'A-ser');
    $repository->setItem('a', 'A2-ser');

    expect($repository->getItem('a'))->toBe('A2-ser');
    expect($repository->getAll())->toEqual(['a' => 'A2-ser']);
})->with('repositories');

it('stores an empty string, which is not the same as storing nothing', function ($repository) {
    /*
     * Only null means "absent". An empty string is a value like any other, and
     * a driver that confused the two would make has() lie about it.
     */
    $repository->setItem('a', '');

    expect($repository->getItem('a'))->toBe('');
    expect($repository->getAll())->toEqual(['a' => '']);
    expect($repository->getItems(['a']))->toEqual(['a' => '']);
})->with('repositories');

/*
|--------------------------------------------------------------------------
| Reading and writing many keys
|--------------------------------------------------------------------------
*/

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
    expect($repository->getAll()['a'])->toBeString();
    expect($repository->getItem('a'))->toBeString();
})->with('repositories');

it('keys missing items as null in getItems', function ($repository) {
    $repository->setItem('a', 'A-ser');

    expect($repository->getItems(['a', 'missing']))->toEqual([
        'a' => 'A-ser',
        'missing' => null,
    ]);
})->with('repositories');

it('answers with nulls when none of the keys exist', function ($repository) {
    expect($repository->getItems(['a', 'b']))->toEqual([
        'a' => null,
        'b' => null,
    ]);
})->with('repositories');

it('preserves the requested order in getItems', function ($repository) {
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect(array_keys($repository->getItems(['b', 'a'])))->toBe(['b', 'a']);
})->with('repositories');

it('answers a repeated key once in getItems', function ($repository) {
    /*
     * The manager can hand a repository the same key twice - get(['a', 'a'])
     * reaches it as it was written - so a driver that positions its answers by
     * index has to cope. phpredis collapses a repeated HMGET field into one
     * value and shifts everything after it, which used to lose 'b' here as
     * well as answering 'a' with null.
     */
    $repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect($repository->getItems(['a', 'a']))->toEqual(['a' => 'A-ser']);
    expect($repository->getItems(['a', 'a', 'b']))->toEqual([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);
    expect($repository->getItems(['a', 'missing', 'a', 'b']))->toEqual([
        'a' => 'A-ser',
        'missing' => null,
        'b' => 'B-ser',
    ]);
})->with('repositories');

it('returns nothing when asked for no keys', function ($repository) {
    $repository->setItem('a', 'A-ser');

    expect($repository->getItems([]))->toBe([]);
})->with('repositories');

it('sets nothing when given no items', function ($repository) {
    $repository->setItem('a', 'A-ser');

    $repository->setItems([]);

    expect($repository->getAll())->toEqual(['a' => 'A-ser']);
})->with('repositories');

it('adds and updates in the same call to setItems', function ($repository) {
    $repository->setItem('a', 'A-ser');

    $repository->setItems([
        'a' => 'A2-ser',
        'b' => 'B-ser',
    ]);

    expect($repository->getAll())->toEqual([
        'a' => 'A2-ser',
        'b' => 'B-ser',
    ]);
})->with('repositories');

it('overwrites an existing item when setting many', function ($repository) {
    $repository->setItem('a', 'A-ser');

    $repository->setItems(['a' => 'A2-ser']);

    expect($repository->getItem('a'))->toBe('A2-ser');
})->with('repositories');

/*
|--------------------------------------------------------------------------
| null deletes
|--------------------------------------------------------------------------
*/

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

it('shrugs at a null value for an item it never had', function ($repository) {
    $repository->setItem('a', 'A-ser');

    $repository->setItem('never-existed', null);
    $repository->setItems(['also-never-existed' => null]);

    expect($repository->getAll())->toEqual(['a' => 'A-ser']);
})->with('repositories');

/*
|--------------------------------------------------------------------------
| What a value may contain
|--------------------------------------------------------------------------
*/

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

it('stores a value far longer than a varchar', function ($repository) {
    /*
     * A serialized array or object is easily kilobytes long, so the database
     * column has to be a text and not a string - 8 KB is past varchar(255)
     * and still well inside MySQL's 64 KB TEXT, which the CI matrix runs on.
     */
    $value = str_repeat('0123456789', 800);

    $repository->setItem('a', $value);

    expect($repository->getItem('a'))->toBe($value);
    expect($repository->getItems(['a'])['a'])->toBe($value);
})->with('repositories');

it('stores a key that is not a plain word', function ($repository) {
    /*
     * Applications namespace their settings, so a key routinely carries dots,
     * colons or slashes. None of them may be read as structure - not by a
     * database, not by the JSON file, and not by a Redis hash field.
     */
    $keys = [
        'mail.from.address' => 'A-ser',
        'tenant:17:theme' => 'B-ser',
        'a/b/c' => 'C-ser',
        'kľúč' => 'D-ser',
    ];

    $repository->setItems($keys);

    expect($repository->getAll())->toEqual($keys);
    expect($repository->getItem('mail.from.address'))->toBe('A-ser');
    expect($repository->getItems(['tenant:17:theme']))->toEqual(['tenant:17:theme' => 'B-ser']);
})->with('repositories');

/*
|--------------------------------------------------------------------------
| getAll() and deleteAll()
|--------------------------------------------------------------------------
*/

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
    expect($repository->getItems(['a', 'b']))->toEqual(['a' => null, 'b' => null]);
})->with('repositories');

it('can be emptied when it is already empty', function ($repository) {
    $repository->deleteAll();

    expect($repository->getAll())->toEqual([]);
})->with('repositories');

it('can be written to again after being emptied', function ($repository) {
    $repository->setItem('a', 'A-ser');
    $repository->deleteAll();

    $repository->setItem('b', 'B-ser');

    expect($repository->getAll())->toEqual(['b' => 'B-ser']);
})->with('repositories');
