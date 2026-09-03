<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;

/*
|--------------------------------------------------------------------------
| The published migration
|--------------------------------------------------------------------------
|
| The migration is not registered with the application - it is published into
| the host app, which owns it from then on - so it is loaded here by hand.
| It reads the table and the connection off the database driver named by
| "settings.migrations.driver", which is what keeps the two from falling out
| of step.
|
*/

function settingsMigration()
{
    return require __DIR__.'/../../database/migrations/2000_01_01_000000_bonsaicms_create_settings_table.php';
}

it('creates the table the database driver is configured with', function () {
    // FeatureTestCase has already run it, so the table is there
    $table = config('settings.drivers.database.table');

    expect(Schema::hasTable($table))->toBeTrue();
    expect(Schema::hasColumns($table, ['key', 'value', 'created_at', 'updated_at']))->toBeTrue();
});

it('makes the key the primary key', function () {
    $table = config('settings.drivers.database.table');

    DB::table($table)->insert(['key' => 'a', 'value' => 'A-ser']);

    expect(fn () => DB::table($table)->insert(['key' => 'a', 'value' => 'A2-ser']))
        ->toThrow(QueryException::class);
});

it('follows the table named by the migration driver', function () {
    config()->set('settings.drivers.somewhere_else', [
        'driver' => 'database',
        'table' => 'settings_somewhere_else',
    ]);
    config()->set('settings.migrations.driver', 'somewhere_else');

    settingsMigration()->up();

    expect(Schema::hasTable('settings_somewhere_else'))->toBeTrue();

    // And the driver of that name can be used straight away
    $repository = app(SettingsRepositoryFactory::class)->driver('somewhere_else');
    $repository->setItem('a', 'A-ser');

    expect($repository->getItem('a'))->toBe('A-ser');
    $this->assertDatabaseHas('settings_somewhere_else', ['key' => 'a']);

    settingsMigration()->down();

    expect(Schema::hasTable('settings_somewhere_else'))->toBeFalse();
});

it('drops the table again on the way down', function () {
    config()->set('settings.drivers.temporary', [
        'driver' => 'database',
        'table' => 'settings_temporary',
    ]);
    config()->set('settings.migrations.driver', 'temporary');

    settingsMigration()->up();
    expect(Schema::hasTable('settings_temporary'))->toBeTrue();

    settingsMigration()->down();
    expect(Schema::hasTable('settings_temporary'))->toBeFalse();

    // down() on a table that is not there is not an error either
    settingsMigration()->down();
});

it('falls back to the default table when the config names no driver', function () {
    config()->set('settings.migrations.driver', 'a_driver_that_does_not_exist');

    settingsMigration()->down();

    expect(Schema::hasTable('bonsaicms_settings'))->toBeFalse();
});
