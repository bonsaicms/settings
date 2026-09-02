<?php

use BonsaiCms\Settings\SettingsSerializer;

beforeEach(function () {
    $this->serializer = new SettingsSerializer;
});

it('serializes null to null', function () {
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
