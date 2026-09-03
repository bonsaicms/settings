<?php

use Tests\Mocks\TestWrappableClass;
use Tests\Mocks\UnserializableClass;
use BonsaiCms\Settings\SerializationWrapper;
use BonsaiCms\Settings\SettingsSerializer;
use BonsaiCms\Settings\Exceptions\SerializeException;

beforeEach(function () {
    $this->serializer = new SettingsSerializer;
});

/*
|--------------------------------------------------------------------------
| The happy path
|--------------------------------------------------------------------------
*/

it('serializes null to null, so that a delete never reaches the store as a value', function () {
    expect($this->serializer->serialize(null))->toBeNull();
});

it('serializes primitives to a non empty string', function ($value) {
    expect($this->serializer->serialize($value))
        ->toBeString()
        ->not->toBeEmpty();
})->with('primitives');

it('serializes objects to a non empty string', function ($value) {
    expect($this->serializer->serialize($value))
        ->toBeString()
        ->not->toBeEmpty();
})->with('objects');

it('stores a value as base64 encoded php serialization', function ($value, $expected) {
    /*
     * The round trip tests below would still pass if this format changed, and
     * the format is exactly what must NOT change silently: every setting
     * already stored in an application is written this way, so a new format
     * makes them all unreadable. Changing these strings is a deliberate act
     * that needs a migration path, not a detail of some refactor.
     */
    expect($this->serializer->serialize($value))->toBe($expected);
})->with([
    'string' => ['test', 'czo0OiJ0ZXN0Ijs='],
    'integer' => [1, 'aToxOw=='],
    'float' => [1.5, 'ZDoxLjU7'],
    'true' => [true, 'YjoxOw=='],
    'false' => [false, 'YjowOw=='],
    'array' => [['a' => 'A'], 'YToxOntzOjE6ImEiO3M6MToiQSI7fQ=='],
]);

/*
|--------------------------------------------------------------------------
| Wrappable objects
|--------------------------------------------------------------------------
*/

it('stores a wrappable object as an envelope instead of the object itself', function () {
    $serialized = unserialize(base64_decode(
        $this->serializer->serialize(new TestWrappableClass('some-secret'))
    ));

    expect($serialized)->toBeInstanceOf(SerializationWrapper::class);
});

it('never puts the wrapped object graph into the stored string', function () {
    /*
     * The point of wrapping: what is stored is the class name plus the small
     * payload the class chose, so a model's attributes - or anything else the
     * object happens to hold - are not written into the settings store.
     */
    $serialized = base64_decode(
        $this->serializer->serialize(new TestWrappableClass('some-secret'))
    );

    expect($serialized)->toContain(TestWrappableClass::class);
    expect($serialized)->toContain('some-secret');
    expect(substr_count($serialized, TestWrappableClass::class))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Failures
|--------------------------------------------------------------------------
*/

it('swallows a value it cannot serialize and stores nothing', function () {
    // The default: config('settings.throwExceptions.serialize') is off
    expect($this->serializer->serialize(new UnserializableClass))->toBeNull();
});

it('throws when the application asked to be told about serialization failures', function () {
    config()->set('settings.throwExceptions.serialize', true);

    $this->serializer->serialize(new UnserializableClass);
})->throws(SerializeException::class);

it('keeps the original failure as the previous exception', function () {
    config()->set('settings.throwExceptions.serialize', true);

    try {
        $this->serializer->serialize(new UnserializableClass);
    } catch (SerializeException $e) {
        expect($e->getPrevious())->toBeInstanceOf(Throwable::class);
        expect($e->getMessage())->toContain('Closure');

        return;
    }

    $this->fail('No SerializeException was thrown.');
});
