<?php

use Tests\Mocks\TestModel;
use Tests\Mocks\TestWrappableClass;
use Tests\Mocks\UnserializableClass;
use BonsaiCms\Settings\SerializationWrapper;
use BonsaiCms\Settings\Exceptions\DeserializeException;

/*
|--------------------------------------------------------------------------
| SerializationWrapper and the SerializableModel trait
|--------------------------------------------------------------------------
|
| The envelope itself, without going through the serializer. What actually
| reaches the store, and what a stored envelope turns back into, is covered in
| SerializerTest and DeserializerTest.
|
*/

/**
 * Reads one of the wrapper's deliberately one letter properties.
 */
function wrapperProperty(SerializationWrapper $wrapper, string $name): mixed
{
    return (new ReflectionProperty(SerializationWrapper::class, $name))->getValue($wrapper);
}

/**
 * An envelope naming a class that is no longer there - the shape an
 * application ends up with after a refactor that forgets the settings store.
 */
function wrapperForAMissingClass(): SerializationWrapper
{
    $wrapper = new SerializationWrapper(new TestWrappableClass('some-secret'));

    (new ReflectionProperty(SerializationWrapper::class, 'c'))
        ->setValue($wrapper, 'Tests\\Mocks\\AClassThatWasRenamed');

    return $wrapper;
}

it('keeps only the class name and the payload the class chose', function () {
    $wrapper = new SerializationWrapper(new TestWrappableClass('some-secret'));

    expect(wrapperProperty($wrapper, 'c'))->toBe(TestWrappableClass::class);
    expect(wrapperProperty($wrapper, 'd'))->toBe(['secret' => 'some-secret']);
});

it('hands the payload back to the class when unwrapping', function () {
    $wrapper = new SerializationWrapper(new TestWrappableClass('some-secret'));

    $unwrapped = $wrapper->unwrap();

    expect($unwrapped)->toBeInstanceOf(TestWrappableClass::class);
    expect($unwrapped)->not->toBe($wrapper);
    expect($unwrapped->getSecret())->toBe('some-secret-was-unwrapped');
});

it('stays short, because the payload is what is stored', function () {
    /*
     * The one letter property names are the reason the envelope is worth
     * having: whatever the object holds, the stored string stays a class name
     * and a small payload.
     */
    expect(strlen(serialize(new SerializationWrapper(new TestWrappableClass('s')))))
        ->toBeLessThan(200);
});

/*
|--------------------------------------------------------------------------
| An envelope that outlived its class
|--------------------------------------------------------------------------
|
| The guard lives in the wrapper rather than in the wrappable classes: an
| envelope is read by whatever code is deployed at the time, which may no
| longer contain the class that wrote it.
|
*/

it('refuses to unwrap a class that no longer exists', function () {
    wrapperForAMissingClass()->unwrap();
})->throws(DeserializeException::class, 'the class no longer exists');

it('refuses to unwrap a class that has stopped being serialization wrappable', function () {
    // The class is still there, it just no longer knows how to unwrap itself
    $wrapper = new SerializationWrapper(new TestWrappableClass('some-secret'));

    (new ReflectionProperty(SerializationWrapper::class, 'c'))
        ->setValue($wrapper, UnserializableClass::class);

    $wrapper->unwrap();
})->throws(DeserializeException::class, 'no longer implements SerializationWrappable');

/*
|--------------------------------------------------------------------------
| The SerializableModel trait
|--------------------------------------------------------------------------
*/

it('stores an eloquent model as nothing but its primary key', function () {
    $model = new TestModel(['name' => 'original name']);
    $model->id = 7;

    expect($model->wrapBeforeSerialization())->toBe(7);

    // Explicitly not the attributes: they are re-read on every unwrap
    expect(wrapperProperty(new SerializationWrapper($model), 'd'))->toBe(7);
});
