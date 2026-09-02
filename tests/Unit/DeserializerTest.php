<?php

use BonsaiCms\Settings\SettingsSerializer;
use BonsaiCms\Settings\SettingsDeserializer;

beforeEach(function () {
    $this->serializer = new SettingsSerializer;
    $this->deserializer = new SettingsDeserializer;
});

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
