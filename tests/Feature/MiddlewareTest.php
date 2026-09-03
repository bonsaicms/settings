<?php

use Illuminate\Support\Facades\Route;
use Tests\Mocks\ThrowingSettingsRepository;
use BonsaiCms\Settings\Contracts\SettingsManager;
use BonsaiCms\Settings\Contracts\SettingsRepository;
use BonsaiCms\Settings\Http\Middleware\LoadSettings;
use BonsaiCms\Settings\Http\Middleware\SaveSettings;

/*
|--------------------------------------------------------------------------
| The two middleware
|--------------------------------------------------------------------------
|
| Neither is registered by the package - an application appends them itself -
| so what is tested here is that they do what the README promises when it
| does: one query up front and none afterwards, and a write only when
| something actually changed.
|
| Like everything else in tests/Feature this knows nothing about the driver it
| is running against, so "no more queries" is asserted by taking the
| repository away rather than by counting SQL.
|
*/

beforeEach(function () {
    $this->manager = app(SettingsManager::class);
});

it('loads every setting before the request is handled', function () {
    settings(['a' => 'A', 'b' => 'B']);
    settings()->save();
    $this->manager->refresh();

    Route::get('/loaded', function () {
        /*
         * LoadSettings has already run, so the manager knows the whole key
         * set. Taking its repository away proves the rest of the request is
         * served from memory - whatever the backend.
         */
        app(SettingsManager::class)->setRepository(new ThrowingSettingsRepository);

        return response()->json([
            'a' => settings('a'),
            'many' => settings(['a', 'b'])->toArray(),
            'has' => settings()->has('a'),
            'missing' => settings('missing'),
            'all' => settings()->all()->toArray(),
        ]);
    })->middleware(LoadSettings::class);

    $this->get('/loaded')->assertOk()->assertExactJson([
        'a' => 'A',
        'many' => ['a' => 'A', 'b' => 'B'],
        'has' => true,
        'missing' => null,
        'all' => ['a' => 'A', 'b' => 'B'],
    ]);
});

it('does not swallow the request it wraps', function () {
    Route::get('/passthrough', fn () => 'handled')->middleware(LoadSettings::class);

    $this->get('/passthrough')->assertOk()->assertSee('handled');
});

it('saves what the request changed', function () {
    Route::get('/write', function () {
        settings('a', 'A');
        settings(['b' => 'B']);

        return 'written';
    })->middleware(SaveSettings::class);

    $this->get('/write')->assertOk();

    $this->manager->refresh();

    expect(settings('a'))->toBe('A');
    expect(settings('b'))->toBe('B');
});

it('saves after the response has been generated, not before', function () {
    /*
     * SaveSettings runs on the way out, so a value set anywhere in the
     * request - including in a terminable step of the response - is still
     * caught, and a request that fails before returning saves nothing.
     */
    Route::get('/write-late', function () {
        settings('a', 'A');

        return response('written')->withHeaders([
            'X-Dirty' => settings()->isDirty() ? 'yes' : 'no',
        ]);
    })->middleware(SaveSettings::class);

    $this->get('/write-late')->assertOk()->assertHeader('X-Dirty', 'yes');

    $this->manager->refresh();

    expect(settings('a'))->toBe('A');
});

it('writes nothing when the request only read', function () {
    $repository = Mockery::mock(SettingsRepository::class);
    $repository->shouldReceive('getAll')->andReturn([]);
    $repository->shouldReceive('getItem')->andReturn(null);
    $repository->shouldReceive('setItem')->never();
    $repository->shouldReceive('setItems')->never();

    $this->manager->setRepository($repository);

    Route::get('/read-only', function () {
        settings('a');
        settings()->all();

        return 'read';
    })->middleware([LoadSettings::class, SaveSettings::class]);

    $this->get('/read-only')->assertOk();

    expect($this->manager->isDirty())->toBeFalse();
});

it('writes nothing when the request did not touch the settings at all', function () {
    $repository = Mockery::mock(SettingsRepository::class);
    $repository->shouldReceive('setItem')->never();
    $repository->shouldReceive('setItems')->never();

    $this->manager->setRepository($repository);

    Route::get('/untouched', fn () => 'nothing to do')->middleware(SaveSettings::class);

    $this->get('/untouched')->assertOk();
});

it('loads once and saves once when both are registered', function () {
    settings(['a' => 'A']);
    settings()->save();
    $this->manager->refresh();

    Route::get('/both', function () {
        // LoadSettings has run: reading is free, and so is knowing what is absent
        app(SettingsManager::class)->setRepository(new ThrowingSettingsRepository);

        expect(settings('a'))->toBe('A');
        expect(settings('missing'))->toBeNull();

        return 'ok';
    })->middleware([LoadSettings::class, SaveSettings::class]);

    $this->get('/both')->assertOk();
});
