<?php

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use BonsaiCms\Settings\Repositories\RedisSettingsRepository;

/*
|--------------------------------------------------------------------------
| RedisSettingsRepository, against a mocked connection
|--------------------------------------------------------------------------
|
| The driver's behaviour against a real server lives in
| tests/Feature/RedisSettingsRepositoryTest and in the shared contract
| dataset - but both of those skip when there is no Redis to talk to, and CI
| is the only place the phpredis client is exercised at all.
|
| What is pinned here is the one thing the two clients genuinely disagree
| about, by handing the driver each client's answer verbatim. It needs no
| server, so it runs everywhere.
|
*/

/**
 * A driver talking to the given mocked connection, over the hash "settings".
 */
function repositoryOver(object $connection): RedisSettingsRepository
{
    $factory = Mockery::mock(RedisFactory::class);
    $factory->shouldReceive('connection')->andReturn($connection);

    return new RedisSettingsRepository($factory, ['key' => 'settings']);
}

/**
 * @param  mixed  $hmgetAnswer  what Laravel's connection returns for hmget
 * @param  array<int, string>|null  $askedFor  the fields the driver asked for
 */
function redisRepository($hmgetAnswer, ?array &$askedFor = null): RedisSettingsRepository
{
    $connection = Mockery::mock();

    $connection->shouldReceive('hmget')
        ->andReturnUsing(function ($key, $fields) use ($hmgetAnswer, &$askedFor) {
            $askedFor = $fields;

            return $hmgetAnswer;
        });

    return repositoryOver($connection);
}

it('reads a predis answer, which is a positional list with nulls', function () {
    $repository = redisRepository(['A-ser', null]);

    expect($repository->getItems(['a', 'missing']))->toBe([
        'a' => 'A-ser',
        'missing' => null,
    ]);
});

it('reads a phpredis answer, which reports a missing field as false', function () {
    $repository = redisRepository(['A-ser', false]);

    expect($repository->getItems(['a', 'missing']))->toBe([
        'a' => 'A-ser',
        'missing' => null,
    ]);
});

it('asks for a repeated key only once', function () {
    /*
     * This is the difference. phpredis answers HMGET with an array keyed by
     * field, which Laravel array_values() back into a list - so a field asked
     * for twice comes back once, and every position after it slides. Asking
     * ['a', 'a', 'b'] would then read 'b' off the end of the answer and call
     * it absent.
     *
     * Deduplicating before the call is what makes both clients agree, and the
     * assertion on $askedFor is what keeps that from being undone.
     */
    $repository = redisRepository(['A-ser', 'B-ser'], $askedFor);

    expect($repository->getItems(['a', 'a', 'b']))->toBe([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect($askedFor)->toBe(['a', 'b']);
});

it('asks for the keys as a list, whatever the caller passed', function () {
    // An answer positioned by index is only correct against a 0-indexed list
    $repository = redisRepository(['A-ser', 'B-ser'], $askedFor);

    expect($repository->getItems([3 => 'a', 7 => 'b']))->toBe([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    expect($askedFor)->toBe(['a', 'b']);
});

it('does not go to redis at all when there is nothing to ask for', function () {
    $connection = Mockery::mock();
    $connection->shouldReceive('hmget')->never();

    expect(repositoryOver($connection)->getItems([]))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| The commands the other methods send
|--------------------------------------------------------------------------
|
| A mocked connection is the only place these can be pinned on a machine with
| no Redis, and CI is the only place phpredis runs at all - so what each
| method sends is worth asserting here rather than only through a live server.
|
*/

it('splits a write into one HDEL and one HMSET', function () {
    $connection = Mockery::mock();

    $connection->shouldReceive('hdel')->with('settings', 'gone', 'also-gone')->once();
    $connection->shouldReceive('hmset')->with('settings', ['a' => 'A-ser', 'b' => 'B-ser'])->once();

    repositoryOver($connection)->setItems([
        'a' => 'A-ser',
        'gone' => null,
        'b' => 'B-ser',
        'also-gone' => null,
    ]);
});

it('sends no command for a half of the write that is empty', function () {
    $connection = Mockery::mock();

    $connection->shouldReceive('hdel')->never();
    $connection->shouldReceive('hmset')->with('settings', ['a' => 'A-ser'])->once();

    repositoryOver($connection)->setItems(['a' => 'A-ser']);
});

it('leaves nothing out of HGETALL, since every field of a hash is present', function () {
    $connection = Mockery::mock();

    $connection->shouldReceive('hgetall')->with('settings')->once()
        ->andReturn(['a' => 'A-ser', 'b' => '']);

    // The empty string is a value like any other, so it must survive
    expect(repositoryOver($connection)->getAll())->toBe(['a' => 'A-ser', 'b' => '']);
});
