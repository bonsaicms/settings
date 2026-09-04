<?php

namespace Tests\Mocks;

use RuntimeException;
use BonsaiCms\Settings\Contracts\SettingsRepository;

/**
 * A repository that refuses to be touched.
 *
 * Swapping it into the manager is how a test asserts that the write behind
 * cache really is answering on its own, whatever driver the suite is running
 * against - counting database queries would only prove it for one of them.
 */
class ThrowingSettingsRepository implements SettingsRepository
{
    public function setItem(string $key, ?string $value): void
    {
        static::fail('setItem');
    }

    public function setItems(array $items): void
    {
        static::fail('setItems');
    }

    public function getItem(string $key): ?string
    {
        static::fail('getItem');
    }

    public function getItems(array $keys): array
    {
        static::fail('getItems');
    }

    public function getAll(): array
    {
        static::fail('getAll');
    }

    public function deleteAll(): void
    {
        static::fail('deleteAll');
    }

    protected static function fail(string $method): never
    {
        throw new RuntimeException(
            "The settings manager reached the repository through {$method}(), "
            .'but everything was supposed to be answered from its cache.'
        );
    }
}
