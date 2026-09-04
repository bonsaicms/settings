<?php

namespace BonsaiCms\Settings\Contracts;

use Illuminate\Support\Collection;

interface SettingsManager
{
    public function getRepository(): SettingsRepository;

    public function setRepository(SettingsRepository $repository): void;

    public function getSerializer(): SettingsSerializer;

    public function setSerializer(SettingsSerializer $serializer): void;

    public function getDeserializer(): SettingsDeserializer;

    public function setDeserializer(SettingsDeserializer $deserializer): void;

    /**
     * Write into the in-memory cache: one key and a value, or a map of pairs.
     * Nothing reaches the store until save() runs.
     *
     * @param  string|array<string, mixed>|Collection<string, mixed>  $keyOrPairs
     */
    public function set(string|array|Collection $keyOrPairs, mixed $value = null): void;

    /**
     * Read one key, or a list of keys - which answers a Collection keyed off
     * the requested keys, in the order they were asked for.
     *
     * @param  string|array<int, string>|Collection<int, string>  $keyOrKeys
     */
    public function get(string|array|Collection $keyOrKeys): mixed;

    public function has(string $key): bool;

    /**
     * @return Collection<string, mixed>
     */
    public function all(): Collection;

    public function save(): void;

    public function deleteAll(): void;

    public function refresh(): void;

    public function isDirty(): bool;
}
