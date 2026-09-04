<?php

namespace BonsaiCms\Settings\Contracts;

interface SettingsRepository
{
    /**
     * Store one already serialized value.
     *
     * A null value means "absent" everywhere in this package, so it removes
     * the key instead of storing anything - a repository that stored a null
     * would make has() report a missing setting as present.
     */
    public function setItem(string $key, ?string $value): void;

    /**
     * @param  array<string, string|null>  $items
     */
    public function setItems(array $items): void;

    public function getItem(string $key): ?string;

    /**
     * Answer in the order the keys were asked for, with null for every key
     * that is not stored. The same key may be asked for more than once.
     *
     * @param  array<int, string>  $keys
     * @return array<string, string|null>
     */
    public function getItems(array $keys): array;

    /**
     * @return array<string, string>
     */
    public function getAll(): array;

    public function deleteAll(): void;
}
