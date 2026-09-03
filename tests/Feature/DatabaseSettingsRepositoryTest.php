<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use BonsaiCms\Settings\Models\Setting;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;
use BonsaiCms\Settings\Repositories\DatabaseSettingsRepository;

/**
 * The behaviour every driver shares lives in SettingsRepositoryTest; what is
 * here is what only the database driver can get wrong - the rows it leaves
 * behind, and the fact that one model class has to serve several drivers
 * pointing at different tables and connections.
 *
 * These run whatever SETTINGS_DRIVER the suite is pointed at, because they
 * resolve the database driver by name rather than through the default.
 */

beforeEach(function () {
    $this->repository = app(SettingsRepositoryFactory::class)
        ->driver('database');

    $this->repository->deleteAll();

    $this->table = config('settings.drivers.database.table');
});

it('stores the value in the configured table', function () {
    $this->repository->setItem('a', 'A-ser');

    $this->assertDatabaseHas($this->table, [
        'key' => 'a',
        'value' => 'A-ser',
    ]);
});

it('stores one row per setting and never a second one for the same key', function () {
    $this->repository->setItem('a', 'A-ser');
    $this->repository->setItem('a', 'A2-ser');
    $this->repository->setItems(['a' => 'A3-ser']);

    $this->assertDatabaseCount($this->table, 1);
    $this->assertDatabaseHas($this->table, ['key' => 'a', 'value' => 'A3-ser']);
});

it('fills in the timestamps', function () {
    $this->repository->setItem('a', 'A-ser');

    $row = DB::table($this->table)->where('key', 'a')->first();

    expect($row->created_at)->not->toBeNull();
    expect($row->updated_at)->not->toBeNull();
});

it('leaves no rows behind after deleting everything', function () {
    $this->repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect(Setting::count())->toBe(2);

    // deleteAll() used to call truncate(), which blows up on SQLite
    $this->repository->deleteAll();

    expect(Setting::count())->toBe(0);
    $this->assertDatabaseCount($this->table, 0);
});

it('deletes the rows of the items set to null and no others', function () {
    $this->repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
        'c' => 'C-ser',
    ]);

    $this->repository->setItems([
        'a' => null,
        'b' => 'B2-ser',
    ]);

    $this->assertDatabaseMissing($this->table, ['key' => 'a']);
    $this->assertDatabaseHas($this->table, ['key' => 'b', 'value' => 'B2-ser']);
    $this->assertDatabaseHas($this->table, ['key' => 'c', 'value' => 'C-ser']);
    $this->assertDatabaseCount($this->table, 2);
});

it('writes many items inside the caller transaction', function () {
    /*
     * setItems() opens a transaction of its own, so a half applied save
     * cannot happen - and because it nests, a save made inside a transaction
     * the application opened is rolled back with it rather than escaping.
     */
    DB::beginTransaction();

    $this->repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    DB::rollBack();

    expect($this->repository->getAll())->toEqual([]);
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
})->skip(
    fn () => env('DB_DRIVER', 'sqlite') !== 'sqlite',
    'The second connection this test opens is an in-memory SQLite one.'
);

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

    $other = app(SettingsRepositoryFactory::class)->driver('other');
    $other->setItem('a', 'A-ser');

    $this->assertDatabaseHas('other_settings', ['key' => 'a']);
    $this->assertDatabaseMissing($this->table, ['key' => 'a']);

    // The two drivers share a model class but must not share its table
    expect($this->repository->getItem('a'))->toBeNull();
    expect($other->getItem('a'))->toBe('A-ser');

    // And the model's own fallback table is not disturbed by either of them
    expect((new Setting)->getTable())->toBe('bonsaicms_settings');
});

it('leaves the model class alone between queries', function () {
    /*
     * The repository builds a fresh model per query and configures it there,
     * which is what lets one model class serve drivers on different tables.
     * Reaching for a static or a shared instance instead would leak the last
     * driver's table into everything else using the model.
     */
    config()->set('settings.drivers.database.table', $this->table);

    $this->repository->setItem('a', 'A-ser');

    expect((new Setting)->getTable())->toBe('bonsaicms_settings');
    expect((new Setting)->getConnectionName())->toBeNull();
});

it('reads and writes through the model class the driver names', function () {
    $repository = new DatabaseSettingsRepository([
        'table' => $this->table,
        'model' => Setting::class,
    ]);

    $repository->setItem('a', 'A-ser');

    expect(Setting::find('a')->value)->toBe('A-ser');
    expect($repository->getItem('a'))->toBe('A-ser');
});
