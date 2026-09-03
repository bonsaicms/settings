<?php

use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;
use BonsaiCms\Settings\Contracts\SettingsSerializer;
use BonsaiCms\Settings\Contracts\SettingsDeserializer;
use BonsaiCms\Settings\SettingsManager;

/*
|--------------------------------------------------------------------------
| SettingsManager, against mocked collaborators
|--------------------------------------------------------------------------
|
| The manager is built here with mocks of all three collaborators, so these
| tests assert on *when* it reaches for the repository and not only on what
| comes back. A change to the caching therefore breaks this file even when the
| observable behaviour is unchanged - that is deliberate, and the expectations
| are meant to be updated on purpose rather than relaxed.
|
| A Mockery mock throws when a method it has no expectation for is called, so
| every "never asks the repository" claim below is already enforced by the
| absence of an expectation; the explicit ->never() calls are there where that
| absence is the whole point of the test.
|
| Anything that needs a real repository - a driver handing back the wrong
| shape, the migration, persistence across a refresh - belongs in
| tests/Feature, where the mocks cannot hide it.
|
*/

beforeEach(function () {
    $this->settingsRepository = Mockery::mock(SettingsRepository::class);
    $this->settingsSerializer = Mockery::mock(SettingsSerializer::class);
    $this->settingsDeserializer = Mockery::mock(SettingsDeserializer::class);

    $this->manager = new SettingsManager(
        $this->settingsRepository,
        $this->settingsSerializer,
        $this->settingsDeserializer
    );
});

/*
|--------------------------------------------------------------------------
| Collaborators
|--------------------------------------------------------------------------
*/

it('holds the repository it was built with and lets it be swapped', function () {
    expect($this->manager->getRepository())->toBe($this->settingsRepository);

    $second = Mockery::mock(SettingsRepository::class);
    $this->manager->setRepository($second);

    expect($this->manager->getRepository())->toBe($second);
});

it('holds the serializer it was built with and lets it be swapped', function () {
    expect($this->manager->getSerializer())->toBe($this->settingsSerializer);

    $second = Mockery::mock(SettingsSerializer::class);
    $this->manager->setSerializer($second);

    expect($this->manager->getSerializer())->toBe($second);
});

it('holds the deserializer it was built with and lets it be swapped', function () {
    expect($this->manager->getDeserializer())->toBe($this->settingsDeserializer);

    $second = Mockery::mock(SettingsDeserializer::class);
    $this->manager->setDeserializer($second);

    expect($this->manager->getDeserializer())->toBe($second);
});

/*
|--------------------------------------------------------------------------
| Reading one key
|--------------------------------------------------------------------------
*/

it('reads one item through the repository and deserializes it', function () {
    $this->settingsRepository
        ->shouldReceive('getItem')->with('a')->once()->andReturn('A-ser');

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');

    expect($this->manager->get('a'))->toBe('A');
});

it('does not deserialize an item the repository does not have', function () {
    $this->settingsRepository
        ->shouldReceive('getItem')->with('a')->once()->andReturn(null);

    $this->settingsDeserializer->shouldReceive('deserialize')->never();

    expect($this->manager->get('a'))->toBeNull();
});

it('asks the repository once for a key it has already read', function () {
    $this->settingsRepository
        ->shouldReceive('getItem')->with('a')->once()->andReturn('A-ser');

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');

    expect($this->manager->get('a'))->toBe('A');
    expect($this->manager->get('a'))->toBe('A');
    expect($this->manager->has('a'))->toBeTrue();
});

it('asks the repository once for a key it has already missed', function () {
    // The miss is cached too, or every has() on an absent key would be a query
    $this->settingsRepository
        ->shouldReceive('getItem')->with('a')->once()->andReturn(null);

    expect($this->manager->get('a'))->toBeNull();
    expect($this->manager->get('a'))->toBeNull();
    expect($this->manager->has('a'))->toBeFalse();
});

it('does not ask the repository for a key it has just been given', function () {
    $this->settingsRepository->shouldReceive('getItem')->never();

    $this->manager->set('a', 'A');

    expect($this->manager->get('a'))->toBe('A');
});

/*
|--------------------------------------------------------------------------
| Reading many keys
|--------------------------------------------------------------------------
*/

it('reads many items in a single repository call', function () {
    $this->settingsRepository
        ->shouldReceive('getItems')->with(['a', 'b'])->once()
        ->andReturn(['a' => 'A-ser', 'b' => 'B-ser']);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');
    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('B-ser')->once()->andReturn('B');

    expect($this->toArray($this->manager->get(['a', 'b'])))
        ->toBe(['a' => 'A', 'b' => 'B']);
});

it('keeps a missing key in the result as null', function () {
    $this->settingsRepository
        ->shouldReceive('getItems')->with(['a', 'b'])->once()
        ->andReturn(['a' => 'A-ser', 'b' => null]);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');

    expect($this->toArray($this->manager->get(['a', 'b'])))
        ->toBe(['a' => 'A', 'b' => null]);
});

it('deserializes nothing when none of the keys exist', function () {
    $this->settingsRepository
        ->shouldReceive('getItems')->with(['a', 'b'])->once()
        ->andReturn(['a' => null, 'b' => null]);

    $this->settingsDeserializer->shouldReceive('deserialize')->never();

    expect($this->toArray($this->manager->get(['a', 'b'])))
        ->toBe(['a' => null, 'b' => null]);
});

it('asks the repository only for the keys it does not have yet', function () {
    $this->manager->set('a', 'A');

    $this->settingsRepository
        ->shouldReceive('getItems')->with(['b'])->once()
        ->andReturn(['b' => 'B-ser']);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('B-ser')->once()->andReturn('B');

    expect($this->toArray($this->manager->get(['a', 'b'])))
        ->toBe(['a' => 'A', 'b' => 'B']);
});

it('asks the repository for nothing when every key is already cached', function () {
    $this->manager->set(['a' => 'A', 'b' => 'B']);

    $this->settingsRepository->shouldReceive('getItems')->never();

    expect($this->toArray($this->manager->get(['a', 'b'])))
        ->toBe(['a' => 'A', 'b' => 'B']);
});

it('returns the keys in the order they were asked for', function () {
    /*
     * Every repository guarantees this for getItems(), so the manager must not
     * lose it - and it is easy to lose, because part of an answer can come
     * from the cache, whose order is the order things were first touched.
     */
    $this->manager->set('b', 'B');

    $this->settingsRepository
        ->shouldReceive('getItems')->with(['a'])->once()
        ->andReturn(['a' => 'A-ser']);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');

    expect(array_keys($this->toArray($this->manager->get(['a', 'b']))))
        ->toBe(['a', 'b']);
});

it('asks the repository once for keys it has already missed', function () {
    $this->settingsRepository
        ->shouldReceive('getItems')->with(['a', 'b'])->once()
        ->andReturn(['a' => null, 'b' => null]);

    $this->manager->get(['a', 'b']);

    expect($this->toArray($this->manager->get(['a', 'b'])))
        ->toBe(['a' => null, 'b' => null]);
});

/*
|--------------------------------------------------------------------------
| has()
|--------------------------------------------------------------------------
*/

it('reports a value it holds as present and anything else as absent', function () {
    $this->settingsRepository
        ->shouldReceive('getItem')->with('missing')->once()->andReturn(null);

    $this->manager->set('a', 'A');

    expect($this->manager->has('a'))->toBeTrue();
    expect($this->manager->has('missing'))->toBeFalse();
});

it('reports falsy values as present, since only null means absent', function ($value) {
    $this->manager->set('a', $value);

    expect($this->manager->has('a'))->toBeTrue();
})->with([
    'false' => [false],
    'zero' => [0],
    'empty string' => [''],
    'empty array' => [[]],
]);

/*
|--------------------------------------------------------------------------
| all()
|--------------------------------------------------------------------------
*/

it('loads everything through the repository and deserializes it', function () {
    $this->settingsRepository
        ->shouldReceive('getAll')->once()
        ->andReturn(['a' => 'A-ser', 'b' => 'B-ser']);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');
    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('B-ser')->once()->andReturn('B');

    expect($this->manager->all())->toBeInstanceOf(Collection::class);
    expect($this->toArray($this->manager->all()))->toBe(['a' => 'A', 'b' => 'B']);
});

it('asks the repository for everything only once', function () {
    $this->settingsRepository->shouldReceive('getAll')->once()->andReturn([]);

    $this->manager->all();
    $this->manager->all();

    expect($this->toArray($this->manager->all()))->toBe([]);
});

it('lets an unsaved value win over what is stored, without decoding it twice', function () {
    $this->manager->set('a', 'local A');

    $this->settingsRepository
        ->shouldReceive('getAll')->once()
        ->andReturn(['a' => 'A-ser', 'b' => 'B-ser']);

    // 'a' is already in the cache, so its stored form is never even decoded
    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->never();
    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('B-ser')->once()->andReturn('B');

    expect($this->toArray($this->manager->all()))
        ->toBe(['b' => 'B', 'a' => 'local A']);
});

it('leaves keys that do not exist out of the result', function () {
    /*
     * Reading a missing key caches a null so the next read stays query free.
     * That negative cache is an implementation detail: null means "absent"
     * everywhere in this package, so all() must not report such a key as a
     * setting - nor a key that has been set to null and not yet saved.
     */
    $this->settingsRepository
        ->shouldReceive('getItem')->with('missing')->once()->andReturn(null);
    $this->settingsRepository
        ->shouldReceive('getAll')->once()->andReturn(['a' => 'A-ser']);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');

    $this->manager->get('missing');
    $this->manager->set('deleted', null);

    expect($this->toArray($this->manager->all()))->toBe(['a' => 'A']);
});

it('answers every read from memory once everything is loaded', function () {
    $this->settingsRepository
        ->shouldReceive('getAll')->once()
        ->andReturn(['a' => 'A-ser']);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');

    $this->manager->all();

    /*
     * loadedAll is what makes the LoadSettings middleware pay off: from here
     * on a cache miss means the key genuinely does not exist, so not one of
     * the reads below may reach the repository.
     */
    $this->settingsRepository->shouldReceive('getItem')->never();
    $this->settingsRepository->shouldReceive('getItems')->never();
    $this->settingsRepository->shouldReceive('getAll')->never();

    expect($this->manager->get('a'))->toBe('A');
    expect($this->manager->get('missing'))->toBeNull();
    expect($this->manager->has('a'))->toBeTrue();
    expect($this->manager->has('missing'))->toBeFalse();
    expect($this->toArray($this->manager->get(['a', 'missing'])))
        ->toBe(['a' => 'A', 'missing' => null]);
});

/*
|--------------------------------------------------------------------------
| Writing into the cache
|--------------------------------------------------------------------------
*/

it('keeps a value in memory without touching the repository', function () {
    $this->settingsRepository->shouldReceive('setItem')->never();
    $this->settingsRepository->shouldReceive('setItems')->never();

    $this->manager->set('a', 'A');

    expect($this->manager->get('a'))->toBe('A');
});

it('overwrites a value that was set before', function () {
    $this->manager->set('a', 'A');
    $this->manager->set('a', 'A2');

    expect($this->manager->get('a'))->toBe('A2');
});

it('takes many pairs at once', function () {
    $this->manager->set(['a' => 'A', 'b' => 'B']);

    expect($this->manager->get('a'))->toBe('A');
    expect($this->manager->get('b'))->toBe('B');
});

it('takes a collection of pairs', function () {
    $this->manager->set(new Collection(['a' => 'A', 'b' => 'B']));

    expect($this->manager->get('a'))->toBe('A');
    expect($this->manager->get('b'))->toBe('B');
});

it('hides a value that has been set to null', function () {
    $this->manager->set('a', 'A');
    $this->manager->set('a', null);

    expect($this->manager->get('a'))->toBeNull();
    expect($this->manager->has('a'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| save()
|--------------------------------------------------------------------------
*/

it('saves a single value with setItem', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')->with('A')->once()->andReturn('A-ser');

    $this->settingsRepository
        ->shouldReceive('setItem')->with('a', 'A-ser')->once();

    $this->manager->set('a', 'A');
    $this->manager->save();
});

it('saves several values with setItems', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')->with('A')->once()->andReturn('A-ser');
    $this->settingsSerializer
        ->shouldReceive('serialize')->with('B')->once()->andReturn('B-ser');

    $this->settingsRepository
        ->shouldReceive('setItems')->with(['a' => 'A-ser', 'b' => 'B-ser'])->once();

    $this->manager->set(['a' => 'A', 'b' => 'B']);
    $this->manager->save();
});

it('does not touch the repository when there is nothing to save', function () {
    $this->settingsRepository->shouldReceive('setItem')->never();
    $this->settingsRepository->shouldReceive('setItems')->never();

    $this->manager->save();
});

it('saves a deletion as a null value, without serializing it', function () {
    $this->settingsSerializer->shouldReceive('serialize')->never();

    $this->settingsRepository
        ->shouldReceive('setItem')->with('a', null)->once();

    $this->manager->set('a', null);
    $this->manager->save();
});

it('writes back the whole cache and not only what changed', function () {
    /*
     * A documented consequence of the write behind design: after all(), every
     * setting is rewritten - and its updated_at bumped - by the next save().
     */
    $this->settingsRepository
        ->shouldReceive('getAll')->once()->andReturn(['a' => 'A-ser']);
    $this->settingsDeserializer
        ->shouldReceive('deserialize')->with('A-ser')->once()->andReturn('A');

    $this->manager->all();
    $this->manager->set('b', 'B');

    $this->settingsSerializer
        ->shouldReceive('serialize')->with('A')->once()->andReturn('A-ser');
    $this->settingsSerializer
        ->shouldReceive('serialize')->with('B')->once()->andReturn('B-ser');

    $this->settingsRepository
        ->shouldReceive('setItems')->with(['a' => 'A-ser', 'b' => 'B-ser'])->once();

    $this->manager->save();
});

it('keeps the values readable after saving them', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')->with('A')->once()->andReturn('A-ser');
    $this->settingsRepository
        ->shouldReceive('setItem')->with('a', 'A-ser')->once();

    $this->manager->set('a', 'A');
    $this->manager->save();

    // Still answered from the cache - saving is not a reason to read again
    $this->settingsRepository->shouldReceive('getItem')->never();

    expect($this->manager->get('a'))->toBe('A');
});

/*
|--------------------------------------------------------------------------
| The dirty flag
|--------------------------------------------------------------------------
*/

it('starts out clean', function () {
    expect($this->manager->isDirty())->toBeFalse();
});

it('becomes dirty after setting one value', function () {
    $this->manager->set('a', 'A');

    expect($this->manager->isDirty())->toBeTrue();
});

it('becomes dirty after setting a value to null, because that is a delete', function () {
    $this->manager->set('a', null);

    expect($this->manager->isDirty())->toBeTrue();
});

it('becomes dirty after setting many values', function () {
    $this->manager->set(['a' => 'A', 'b' => null]);

    expect($this->manager->isDirty())->toBeTrue();
});

it('stays clean when nothing is actually written', function () {
    $this->manager->set([]);

    expect($this->manager->isDirty())->toBeFalse();
});

it('stays clean while only reading', function () {
    $this->settingsRepository
        ->shouldReceive('getItem')->with('a')->once()->andReturn(null);
    $this->settingsRepository->shouldReceive('getAll')->once()->andReturn([]);

    $this->manager->get('a');
    $this->manager->has('a');
    $this->manager->all();

    expect($this->manager->isDirty())->toBeFalse();
});

it('is clean again after a save', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')->with('A')->once()->andReturn('A-ser');
    $this->settingsRepository
        ->shouldReceive('setItem')->with('a', 'A-ser')->once();

    $this->manager->set('a', 'A');
    $this->manager->save();

    expect($this->manager->isDirty())->toBeFalse();
});

it('is clean again after a refresh', function () {
    $this->manager->set('a', 'A');

    $this->manager->refresh();

    expect($this->manager->isDirty())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| refresh() and deleteAll()
|--------------------------------------------------------------------------
*/

it('throws away unsaved changes on a refresh', function () {
    $this->manager->set('a', 'A');

    $this->manager->refresh();

    $this->settingsRepository
        ->shouldReceive('getItem')->with('a')->once()->andReturn(null);

    expect($this->manager->get('a'))->toBeNull();
});

it('reads from the repository again after a refresh', function () {
    $this->settingsRepository->shouldReceive('getAll')->twice()->andReturn([]);

    $this->manager->all();

    // Without loadedAll being reset, this second all() would be free
    $this->manager->refresh();

    $this->manager->all();
});

it('empties the repository and the cache on deleteAll', function () {
    $this->manager->set('a', 'A');

    $this->settingsRepository->shouldReceive('deleteAll')->once();

    $this->manager->deleteAll();

    expect($this->toArray($this->manager->all()))->toBe([]);
});

it('knows the store is empty after deleteAll, without asking again', function () {
    $this->settingsRepository->shouldReceive('deleteAll')->once();

    $this->manager->deleteAll();

    $this->settingsRepository->shouldReceive('getItem')->never();
    $this->settingsRepository->shouldReceive('getItems')->never();
    $this->settingsRepository->shouldReceive('getAll')->never();

    expect($this->manager->get('a'))->toBeNull();
    expect($this->manager->has('a'))->toBeFalse();
    expect($this->toArray($this->manager->all()))->toBe([]);
});

it('deletes everything immediately, without waiting for a save', function () {
    $this->settingsRepository->shouldReceive('deleteAll')->once();
    $this->settingsRepository->shouldReceive('setItem')->never();
    $this->settingsRepository->shouldReceive('setItems')->never();

    $this->manager->deleteAll();
});
