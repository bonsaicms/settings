<?php

use Tests\Mocks\TestWrappableClass;
use BonsaiCms\Settings\SettingsSerializer;
use BonsaiCms\Settings\SettingsDeserializer;

beforeEach(function () {
    $this->serializer = new SettingsSerializer;
    $this->deserializer = new SettingsDeserializer;
});

it('wraps a wrappable object before serialization and unwraps it back', function () {
    $secret = 'some-secret';

    $wrappableObject = new TestWrappableClass($secret);

    $serialized = $this->serializer->serialize($wrappableObject);

    $deserialized = $this->deserializer->deserialize($serialized);

    expect($deserialized->getSecret())
        ->toEqual("{$secret}-was-unwrapped-".get_class($wrappableObject));
});
