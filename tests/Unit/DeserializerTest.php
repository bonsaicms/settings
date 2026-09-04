<?php

use Tests\Mocks\TestWrappableClass;
use BonsaiCms\Settings\SerializationWrapper;
use BonsaiCms\Settings\SettingsSerializer;
use BonsaiCms\Settings\SettingsDeserializer;
use BonsaiCms\Settings\Exceptions\DeserializeException;

beforeEach(function () {
    $this->serializer = new SettingsSerializer;
    $this->deserializer = new SettingsDeserializer;
});

/**
 * A stored value whose class has since been renamed or removed - the shape an
 * application ends up with after a refactor that forgets the settings store.
 */
function storedWrapperForAMissingClass(): string
{
    $wrapper = new SerializationWrapper(new TestWrappableClass('some-secret'));

    $class = new ReflectionProperty(SerializationWrapper::class, 'c');
    $class->setValue($wrapper, 'Tests\\Mocks\\AClassThatWasRenamed');

    return base64_encode(serialize($wrapper));
}

/*
|--------------------------------------------------------------------------
| The happy path
|--------------------------------------------------------------------------
*/

it('deserializes null to null', function () {
    expect($this->deserializer->deserialize(null))->toBeNull();
});

it('round trips primitives', function ($value) {
    $serialized = $this->serializer->serialize($value);

    expect($this->deserializer->deserialize($serialized))->toEqual($value);
})->with('primitives');

it('round trips objects', function ($value) {
    $serialized = $this->serializer->serialize($value);

    expect($this->deserializer->deserialize($serialized))->toEqual($value);
})->with('objects');

it('keeps the type of what it read', function ($value) {
    expect($this->deserializer->deserialize($this->serializer->serialize($value)))
        ->toBe($value);
})->with([
    'string' => ['test'],
    'integer' => [1],
    'float' => [1.5],
    'true' => [true],
    'false' => [false],
    'zero' => [0],
    'empty string' => [''],
]);

it('reads back a value stored by an earlier version of the package', function ($stored, $expected) {
    /*
     * Fixed strings on purpose. Round tripping through this package's own
     * serializer would keep passing if the format changed on both sides at
     * once, and every setting already in an application's database is written
     * in exactly this format.
     */
    expect($this->deserializer->deserialize($stored))->toBe($expected);
})->with([
    'string' => ['czo0OiJ0ZXN0Ijs=', 'test'],
    'integer' => ['aToxOw==', 1],
    'float' => ['ZDoxLjU7', 1.5],
    'true' => ['YjoxOw==', true],
    'false' => ['YjowOw==', false],
]);

it('tells a stored false from a value it could not read', function () {
    /*
     * unserialize() answers false for the string "b:0;" and for anything it
     * cannot parse alike. Getting this wrong either loses every stored false
     * or - worse - reports a damaged entry as false, which is not null, so
     * has() would call a broken setting present.
     */
    expect($this->deserializer->deserialize($this->serializer->serialize(false)))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Wrappable objects
|--------------------------------------------------------------------------
*/

it('unwraps an envelope back into the object it describes', function () {
    $wrappable = new TestWrappableClass('some-secret');

    $deserialized = $this->deserializer->deserialize($this->serializer->serialize($wrappable));

    expect($deserialized)->toBeInstanceOf(TestWrappableClass::class);
    expect($deserialized->getSecret())
        ->toBe('some-secret-was-unwrapped');
});

it('does not wrap a wrappable object that is nested inside another value', function () {
    /*
     * Only a value that is itself wrappable gets the envelope; one sitting
     * inside an array or another object goes through plain serialize(), so it
     * comes back as a snapshot rather than being rebuilt. Worth knowing before
     * putting a model inside an array of settings.
     */
    $deserialized = $this->deserializer->deserialize(
        $this->serializer->serialize(['nested' => new TestWrappableClass('some-secret')])
    );

    expect($deserialized['nested']->getSecret())->toBe('some-secret');
});

/*
|--------------------------------------------------------------------------
| Damaged values
|--------------------------------------------------------------------------
*/

it('reads a damaged value as null', function ($stored) {
    expect($this->deserializer->deserialize($stored))->toBeNull();
})->with([
    'empty string' => [''],
    'not base64 at all' => ['this is not base64 !!'],
    'base64 of something that was never serialized' => [base64_encode('just some text')],
    'a truncated serialized string' => [substr(base64_encode(serialize('test')), 0, 8)],
    'a wrapper whose class is gone' => [fn () => storedWrapperForAMissingClass()],
]);

it('reads a damaged value without raising a php warning', function () {
    /*
     * unserialize() warns rather than throwing, so without handling it here
     * the driver's answer would depend on whatever error handler the host
     * application happens to install - returning null under Laravel's, and a
     * bare false under a handler that suppresses warnings.
     */
    $warnings = [];

    set_error_handler(function ($severity, $message) use (&$warnings) {
        $warnings[] = $message;

        return true;
    });

    try {
        $value = $this->deserializer->deserialize(base64_encode('just some text'));
    } finally {
        restore_error_handler();
    }

    expect($value)->toBeNull();
    expect($warnings)->toBe([]);
});

it('throws when the application asked to be told about deserialization failures', function () {
    (new SettingsDeserializer(throwExceptions: true))
        ->deserialize(base64_encode('just some text'));
})->throws(DeserializeException::class);

it('throws when an envelope names a class that no longer exists', function () {
    (new SettingsDeserializer(throwExceptions: true))
        ->deserialize(storedWrapperForAMissingClass());
})->throws(DeserializeException::class, 'no longer exists');

it('swallows a missing class rather than taking the application down', function () {
    // The production default: one unreadable setting is not worth a 500
    expect($this->deserializer->deserialize(storedWrapperForAMissingClass()))->toBeNull();
});

it('hands back an incomplete object for a plain class that no longer exists', function () {
    /*
     * A KNOWN GAP, pinned here so it cannot change by accident.
     *
     * An object that does not implement SerializationWrappable goes through
     * plain serialize(), and unserialize() answers a __PHP_Incomplete_Class
     * for one whose class has since been renamed - it does not warn and it
     * does not throw, so nothing above catches it. The setting therefore
     * reads as present, and touching any property of it is a fatal error.
     *
     * The wrapped path returns null in the same situation (see the test
     * above). Making this one agree would mean rejecting
     * __PHP_Incomplete_Class in the deserializer; it is left alone for now
     * because it is a behaviour change, not an oversight.
     */
    $deserialized = $this->deserializer->deserialize(
        base64_encode('O:8:"GoneAway":0:{}')
    );

    expect($deserialized)->toBeInstanceOf(__PHP_Incomplete_Class::class);
    expect($deserialized)->not->toBeNull();
});
