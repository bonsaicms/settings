<?php

namespace BonsaiCms\Settings\Repositories;

use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;

class ArraySettingsRepository implements SettingsRepository
{
    protected $storage;

    public function __construct()
    {
        $this->storage = new Collection;
    }

    public function setItem(string $key, $value) : void
    {
        // A null value means "absent", exactly like in DatabaseSettingsRepository
        if ($value === null) {
            $this->storage->forget($key);
        } else {
            $this->storage[$key] = $value;
        }
    }

    public function setItems(array $items) : void
    {
        foreach ($items as $key => $value) {
            $this->setItem($key, $value);
        }
    }

    public function getItem(string $key)
    {
        return $this->storage->get($key);
    }

    public function getItems(array $keys) : array
    {
        return (new Collection($keys))->mapWithKeys(function ($key) {
            return [$key => $this->storage->get($key)];
        })->toArray();
    }

    public function getAll() : array
    {
        return $this->storage->toArray();
    }

    public function deleteAll() : void
    {
        $this->storage = new Collection;
    }
}
