<?php

use BonsaiCms\Settings\Contracts\SettingsRepository;
use BonsaiCms\Settings\Contracts\SettingsSerializer;
use BonsaiCms\Settings\Contracts\SettingsDeserializer;
use BonsaiCms\Settings\SettingsManager;

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

it('gets and sets the repository', function () {
    expect($this->manager->getRepository())->toEqual($this->settingsRepository);

    $secondRepository = Mockery::mock(SettingsRepository::class);

    expect($this->manager->setRepository($secondRepository))->toBeNull();

    expect($this->manager->getRepository())->toEqual($secondRepository);
});

it('gets and sets the serializer', function () {
    expect($this->manager->getSerializer())->toEqual($this->settingsSerializer);

    $secondSerializer = Mockery::mock(SettingsSerializer::class);

    expect($this->manager->setSerializer($secondSerializer))->toBeNull();

    expect($this->manager->getSerializer())->toEqual($secondSerializer);
});

it('gets and sets the deserializer', function () {
    expect($this->manager->getDeserializer())->toEqual($this->settingsDeserializer);

    $secondDeserializer = Mockery::mock(SettingsDeserializer::class);

    expect($this->manager->setDeserializer($secondDeserializer))->toBeNull();

    expect($this->manager->getDeserializer())->toEqual($secondDeserializer);
});

it('calls deleteAll on the repository', function () {
    $this->settingsRepository
        ->shouldReceive('deleteAll')
        ->once();
    $this->manager->deleteAll();

    $this->settingsRepository
        ->shouldReceive('deleteAll')
        ->once();
    $this->manager->deleteAll();
});

it('gets one item', function () {
    $this->settingsRepository
        ->shouldReceive('getItem')
        ->with('a')
        ->once()
        ->andReturn('A-ser');

    $this->settingsDeserializer
        ->shouldReceive('deserialize')
        ->once()
        ->with('A-ser')
        ->andReturn('A');

    expect($this->manager->get('a'))->toEqual('A');
});

it('gets one null item without deserializing it', function () {
    $this->settingsRepository
        ->shouldReceive('getItem')
        ->with('a')
        ->once()
        ->andReturn(null);

    expect($this->manager->get('a'))->toBeNull();
});

it('gets one non null and one null item', function () {
    $this->settingsRepository
        ->shouldReceive('getItems')
        ->with(['a', 'b'])
        ->once()
        ->andReturn([
            'a' => 'A-ser',
            'b' => null,
        ]);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')
        ->once()
        ->with('A-ser')
        ->andReturn('A');

    expect($this->toArray($this->manager->get(['a', 'b'])))->toEqual([
        'a' => 'A',
        'b' => null,
    ]);
});

it('gets two null items', function () {
    $this->settingsRepository
        ->shouldReceive('getItems')
        ->with(['a', 'b'])
        ->once()
        ->andReturn([
            'a' => null,
            'b' => null,
        ]);

    expect($this->toArray($this->manager->get(['a', 'b'])))->toEqual([
        'a' => null,
        'b' => null,
    ]);
});

it('gets two non null items', function () {
    $this->settingsRepository
        ->shouldReceive('getItems')
        ->with(['a', 'b'])
        ->once()
        ->andReturn([
            'a' => 'A-ser',
            'b' => 'B-ser',
        ]);

    $this->settingsDeserializer
        ->shouldReceive('deserialize')
        ->once()
        ->with('A-ser')
        ->andReturn('A');

    $this->settingsDeserializer
        ->shouldReceive('deserialize')
        ->once()
        ->with('B-ser')
        ->andReturn('B');

    expect($this->toArray($this->manager->get(['a', 'b'])))->toEqual([
        'a' => 'A',
        'b' => 'B',
    ]);
});

it('sets one item', function () {
    $this->manager->set('a', 'A');

    expect($this->manager->get('a'))->toEqual('A');
});

it('overrides one item', function () {
    $this->manager->set('a', 'A');
    $this->manager->set('a', 'A2');

    expect($this->manager->get('a'))->toEqual('A2');
});

it('deletes one item', function () {
    $this->manager->set('a', 'A');
    $this->manager->set('a', null);

    expect($this->manager->get('a'))->toBeNull();
});

it('sets one item and saves it', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')
        ->with('A')
        ->andReturn('A-ser');

    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', 'A-ser');

    $this->manager->set('a', 'A');
    $this->manager->save();

    expect($this->manager->get('a'))->toEqual('A');
});

it('reads a saved item from the repository after a refresh', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')
        ->with('A')
        ->andReturn('A-ser');

    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', 'A-ser');

    $this->manager->set('a', 'A');
    $this->manager->save();

    $this->manager->refresh();

    $this->settingsRepository
        ->shouldReceive('getItem')
        ->with('a')
        ->andReturn('A-ser');
    $this->settingsDeserializer
        ->shouldReceive('deserialize')
        ->with('A-ser')
        ->andReturn('A');

    expect($this->manager->get('a'))->toEqual('A');
});

it('overrides one item and saves it', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')
        ->with('A')
        ->andReturn('A-ser');
    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', 'A-ser');
    $this->manager->set('a', 'A');
    $this->manager->save();
    expect($this->manager->get('a'))->toEqual('A');

    $this->settingsSerializer
        ->shouldReceive('serialize')
        ->with('A2')
        ->andReturn('A2-ser');
    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', 'A2-ser');
    $this->manager->set('a', 'A2');
    $this->manager->save();
    expect($this->manager->get('a'))->toEqual('A2');
});

it('reads an overridden item from the repository after a refresh', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')
        ->with('A')
        ->andReturn('A-ser');
    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', 'A-ser');
    $this->manager->set('a', 'A');
    $this->manager->save();
    expect($this->manager->get('a'))->toEqual('A');

    $this->settingsSerializer
        ->shouldReceive('serialize')
        ->with('A2')
        ->andReturn('A2-ser');
    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', 'A2-ser');
    $this->manager->set('a', 'A2');
    $this->manager->save();

    $this->manager->refresh();

    $this->settingsRepository
        ->shouldReceive('getItem')
        ->with('a')
        ->andReturn('A2-ser');
    $this->settingsDeserializer
        ->shouldReceive('deserialize')
        ->with('A2-ser')
        ->andReturn('A2');

    expect($this->manager->get('a'))->toEqual('A2');
});

it('deletes one item and saves it', function () {
    $this->settingsSerializer
        ->shouldReceive('serialize')
        ->with('A')
        ->andReturn('A-ser');
    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', 'A-ser');
    $this->manager->set('a', 'A');
    $this->manager->save();
    expect($this->manager->get('a'))->toEqual('A');

    $this->settingsRepository
        ->shouldReceive('setItem')
        ->with('a', null);
    $this->manager->set('a', null);
    $this->manager->save();
    expect($this->manager->get('a'))->toBeNull();
});

it('sets two items and saves them at once', function () {
    $this->manager->set([
        'a' => 'A',
        'b' => 'B',
    ]);

    $this->settingsSerializer->shouldReceive('serialize')->with('A')->andReturn('A-ser');
    $this->settingsSerializer->shouldReceive('serialize')->with('B')->andReturn('B-ser');

    $this->settingsRepository->shouldReceive('setItems')->with([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $this->manager->save();

    expect($this->manager->get('a'))->toEqual('A');
    expect($this->manager->get('b'))->toEqual('B');
    expect($this->toArray($this->manager->get(['a', 'b'])))->toEqual([
        'a' => 'A',
        'b' => 'B',
    ]);

    $this->manager->refresh();

    $this->settingsRepository->shouldReceive('getItems')->with(['a', 'b'])->andReturn([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $this->settingsDeserializer->shouldReceive('deserialize')->with('A-ser')->andReturn('A');
    $this->settingsDeserializer->shouldReceive('deserialize')->with('B-ser')->andReturn('B');

    expect($this->toArray($this->manager->get(['a', 'b'])))->toEqual([
        'a' => 'A',
        'b' => 'B',
    ]);
    expect($this->manager->get('a'))->toEqual('A');
    expect($this->manager->get('b'))->toEqual('B');
});

it('is dirty after setting a value', function () {
    expect($this->manager->isDirty())->toBeFalse();

    $this->manager->set('a', 'A');

    expect($this->manager->isDirty())->toBeTrue();
});

it('is dirty after setting null', function () {
    expect($this->manager->isDirty())->toBeFalse();

    $this->manager->set('a', null);

    expect($this->manager->isDirty())->toBeTrue();
});

it('is dirty after setting many values', function () {
    expect($this->manager->isDirty())->toBeFalse();

    $this->manager->set([
        'a' => 'A',
        'b' => null,
    ]);

    expect($this->manager->isDirty())->toBeTrue();
});

it('stays clean after setting an empty array', function () {
    expect($this->manager->isDirty())->toBeFalse();

    $this->manager->set([]);

    expect($this->manager->isDirty())->toBeFalse();
});

it('is clean after a refresh', function () {
    expect($this->manager->isDirty())->toBeFalse();
    $this->manager->set('a', 'A');
    expect($this->manager->isDirty())->toBeTrue();

    $this->manager->refresh();

    expect($this->manager->isDirty())->toBeFalse();
});

it('is clean after a save', function () {
    $this->settingsSerializer->shouldReceive('serialize')->with('A')->andReturn('A-ser');
    $this->settingsRepository->shouldReceive('setItem')->with('a', 'A-ser');

    expect($this->manager->isDirty())->toBeFalse();
    $this->manager->set('a', 'A');
    expect($this->manager->isDirty())->toBeTrue();

    $this->manager->save();

    expect($this->manager->isDirty())->toBeFalse();
});
