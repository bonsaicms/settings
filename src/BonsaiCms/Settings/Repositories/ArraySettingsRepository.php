<?php

namespace BonsaiCms\Settings\Repositories;

use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;

/**
 * Keeps the settings in memory for the lifetime of the container, and nowhere
 * else. Meant for tests and for debugging; nothing survives the request.
 *
 * The only driver with nothing to configure, so unlike the others it takes no
 * config array - the factory passes one by name, and a constructor that does
 * not ask for it simply never sees it.
 */
class ArraySettingsRepository implements SettingsRepository
{
    /**
     * @var Collection<string, string>
     */
    protected Collection $storage;

    public function __construct()
    {
        $this->storage = new Collection;
    }

    public function setItem(string $key, ?string $value): void
    {
        // A null value means "absent", exactly like in every other driver
        if ($value === null) {
            $this->storage->forget($key);
        } else {
            $this->storage[$key] = $value;
        }
    }

    public function setItems(array $items): void
    {
        foreach ($items as $key => $value) {
            $this->setItem((string) $key, $value);
        }
    }

    public function getItem(string $key): ?string
    {
        return $this->storage->get($key);
    }

    public function getItems(array $keys): array
    {
        return (new Collection($keys))
            ->mapWithKeys(fn ($key) => [$key => $this->storage->get($key)])
            ->all();
    }

    public function getAll(): array
    {
        return $this->storage->all();
    }

    public function deleteAll(): void
    {
        $this->storage = new Collection;
    }
}
