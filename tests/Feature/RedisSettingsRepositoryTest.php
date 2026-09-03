<?php

use Tests\FeatureTestCase;
use Illuminate\Support\Facades\Redis;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;

/**
 * The behaviour every driver shares lives in SettingsRepositoryTest; what is
 * here is what only the Redis driver can get wrong - that it really is one
 * hash, on the configured connection, under the configured key.
 */

beforeEach(function () {
    FeatureTestCase::requireRedis();

    $this->repository = app(SettingsRepositoryFactory::class)->driver('redis');
    $this->key = config('settings.drivers.redis.key');

    $this->repository->deleteAll();
});

afterEach(function () {
    if (isset($this->repository)) {
        $this->repository->deleteAll();
    }
});

it('keeps every setting in one hash under the configured key', function () {
    $this->repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    // predis answers with a status object, phpredis with the numeric type
    expect((string) Redis::connection()->type($this->key))->toBeIn(['hash', '5']);
    expect(Redis::connection()->hgetall($this->key))->toEqual([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);
});

it('uses the key from the driver config', function () {
    config()->set('settings.drivers.elsewhere', [
        'driver' => 'redis',
        'connection' => 'default',
        'key' => 'settings_elsewhere',
    ]);

    $repository = app(SettingsRepositoryFactory::class)->driver('elsewhere');
    $repository->deleteAll();

    $repository->setItem('a', 'A-ser');

    expect(Redis::connection()->hget('settings_elsewhere', 'a'))->toBe('A-ser');
    expect(Redis::connection()->hget($this->key, 'a'))->toBeIn([null, false]);

    $repository->deleteAll();
});

it('removes the whole hash on deleteAll', function () {
    $this->repository->setItem('a', 'A-ser');

    expect((bool) Redis::connection()->exists($this->key))->toBeTrue();

    $this->repository->deleteAll();

    expect((bool) Redis::connection()->exists($this->key))->toBeFalse();
});

it('removes the field rather than storing a null', function () {
    $this->repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $this->repository->setItem('a', null);

    expect(Redis::connection()->hexists($this->key, 'a'))->toBeFalsy();
    expect(Redis::connection()->hexists($this->key, 'b'))->toBeTruthy();
});

it('reports a missing field as null and never as false', function () {
    /*
     * phpredis answers HGET and HMGET with false, predis with null. The whole
     * package treats null as "absent", so the driver has to normalise it or
     * has() would report a missing setting as present.
     */
    $this->repository->setItem('a', 'A-ser');

    expect($this->repository->getItem('missing'))->toBeNull();
    expect($this->repository->getItems(['a', 'missing'])['missing'])->toBeNull();
    expect($this->repository->getItems(['missing'])['missing'])->toBeNull();
});

it('reads an empty hash as no settings at all', function () {
    expect($this->repository->getAll())->toBe([]);
    expect($this->repository->getItems(['a', 'b']))->toEqual(['a' => null, 'b' => null]);
});

it('talks to the connection the driver names', function () {
    config()->set('database.redis.other', array_merge(
        config('database.redis.default'),
        ['database' => (int) env('REDIS_DB', 0) + 1]
    ));

    config()->set('settings.drivers.other_connection', [
        'driver' => 'redis',
        'connection' => 'other',
        'key' => $this->key,
    ]);

    /*
     * RedisManager reads config/database.php once, when it is first resolved -
     * which beforeEach already did - so a connection added afterwards is only
     * visible to a manager built again.
     */
    app()->forgetInstance('redis');
    app()->forgetInstance('redis.connection');

    $other = app(SettingsRepositoryFactory::class)->driver('other_connection');
    $other->deleteAll();

    $this->repository->setItem('a', 'A-ser');

    // Same key, different Redis database, so the value must not be visible
    expect($other->getItem('a'))->toBeNull();
    expect($this->repository->getItem('a'))->toBe('A-ser');

    $other->deleteAll();
});
