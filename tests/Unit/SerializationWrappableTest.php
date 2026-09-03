<?php

use Tests\Mocks\TestModel;
use Tests\Mocks\TestWrappableClass;
use BonsaiCms\Settings\SerializationWrapper;
use BonsaiCms\Settings\Exceptions\DeserializeException;

/*
|--------------------------------------------------------------------------
| SerializationWrapper and SerializableModelTrait
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
function wrapperProperty(SerializationWrapper $wrapper, string $name)
{
    return (new ReflectionProperty(SerializationWrapper::class, $name))->getValue($wrapper);
}

it('keeps only the class name and the payload the class chose', function () {
    $wrapper = new SerializationWrapper(new TestWrappableClass('some-secret'));

    expect(wrapperProperty($wrapper, 'c'))->toBe(TestWrappableClass::class);
    expect(wrapperProperty($wrapper, 'd'))->toBe(['secret' => 'some-secret']);
});

it('hands the class name and the payload back to the class when unwrapping', function () {
    $wrapper = new SerializationWrapper(new TestWrappableClass('some-secret'));

    $unwrapped = $wrapper->unwrap();

    expect($unwrapped)->toBeInstanceOf(TestWrappableClass::class);
    expect($unwrapped)->not->toBe($wrapper);
    expect($unwrapped->getSecret())
        ->toBe('some-secret-was-unwrapped-'.TestWrappableClass::class);
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
| SerializableModelTrait
|--------------------------------------------------------------------------
*/

it('stores an eloquent model as nothing but its primary key', function () {
    $model = new TestModel(['name' => 'original name']);
    $model->id = 7;

    expect(TestModel::wrapBeforeSerialization($model))->toBe(7);

    // Explicitly not the attributes: they are re-read on every unwrap
    expect(wrapperProperty(new SerializationWrapper($model), 'd'))->toBe(7);
});

it('refuses to unwrap a class that is not an eloquent model', function () {
    TestModel::unwrapAfterSerialization(TestWrappableClass::class, 1);
})->throws(DeserializeException::class, 'Cannot deserialize Eloquent model');

it('refuses to unwrap a class that no longer exists', function () {
    TestModel::unwrapAfterSerialization('Tests\\Mocks\\AClassThatWasRenamed', 1);
})->throws(DeserializeException::class, 'Cannot deserialize Eloquent model');
