<?php

namespace BonsaiCms\Settings;

use Illuminate\Support\Collection;

class SettingsManager implements Contracts\SettingsManager
{
    protected $cache;
    protected $loadedAll = false;

    /**
     * @var Contracts\SettingsRepository
     */
    protected $repository;

    /**
     * @var Contracts\SettingsSerializer
     */
    protected $serializer;

    /**
     * @var Contracts\SettingsDeserializer
     */
    protected $deserializer;

    /**
     * @param Contracts\SettingsRepository $repository
     * @param Contracts\SettingsSerializer $serializer
     * @param Contracts\SettingsDeserializer $deserializer
     */
    public function __construct(
        Contracts\SettingsRepository $repository,
        Contracts\SettingsSerializer $serializer,
        Contracts\SettingsDeserializer $deserializer
    )
    {
        $this->setRepository($repository);
        $this->setSerializer($serializer);
        $this->setDeserializer($deserializer);

        $this->initializeCache();
    }

    protected function initializeCache ()
    {
        $this->cache = new Collection;
    }

    /**
     * @return Contracts\SettingsRepository
     */
    public function getRepository(): Contracts\SettingsRepository
    {
        return $this->repository;
    }

    /**
     * @param Contracts\SettingsRepository $repository
     */
    public function setRepository(Contracts\SettingsRepository $repository) : void
    {
        $this->repository = $repository;
    }

    /**
     * @return Contracts\SettingsSerializer
     */
    public function getSerializer(): Contracts\SettingsSerializer
    {
        return $this->serializer;
    }

    /**
     * @param Contracts\SettingsSerializer $serializer
     */
    public function setSerializer(Contracts\SettingsSerializer $serializer) : void
    {
        $this->serializer = $serializer;
    }

    /**
     * @return Contracts\SettingsDeserializer
     */
    public function getDeserializer(): Contracts\SettingsDeserializer
    {
        return $this->deserializer;
    }

    /**
     * @param Contracts\SettingsDeserializer $deserializer
     */
    public function setDeserializer(Contracts\SettingsDeserializer $deserializer) : void
    {
        $this->deserializer = $deserializer;
    }

    public function set($keyOrPairs, $valueOrNull = null) : void
    {
        if (is_string($keyOrPairs)) {
            $this->setOne($keyOrPairs, $valueOrNull);
        } else {
            $this->setMany($keyOrPairs);
        }
    }

    protected function setOne(string $key, $value)
    {
        $this->cache[$key] = $value;

        return $this;
    }

    /**
     * @param array | Collection $items
     */
    protected function setMany($items = [])
    {
        foreach ($items as $key => $value) {
            $this->setOne($key, $value);
        }

        return $this;
    }

    public function get($keyOrKeys)
    {
        $this->autoloadIfNeeded();

        return (is_string($keyOrKeys))
            ? $this->getOne($keyOrKeys)
            : $this->getMany($keyOrKeys);
    }

    protected function autoloadIfNeeded()
    {
        if ( ! $this->loadedAll && config('settings.autoload')) {
            $this->all();
        }
    }

    public function has($key) : bool
    {
        return ($this->getOne($key) !== null);
    }

    public function all()
    {
        if ( ! $this->loadedAll) {
            $this->cache = $this->toCollection(
                $this
                    ->repository
                    ->getAll()
            )
                ->diffKeys($this->getCachedKeys()->flip())
            ->map(function ($serializedValue) {
                return $this->deserializer->deserialize($serializedValue);
            })
            ->merge($this->cache);

            $this->loadedAll = true;
        }

        return $this->cache;
    }

    protected function getOne($key)
    {
        if ( ! $this->isCached($key) && $this->loadedAll === false) {
            $this->cache[$key] = $this->deserializer->deserialize(
                $this->repository->getItem($key)
            );
        }

        return $this->cache->get($key);
    }

    protected function getMany($keys): Collection
    {
        $keys = $this->toCollection($keys);
        $missingKeys = $keys->diff($this->getCachedKeys())->values();

        // Load missing (non-cached) items
        if ($missingKeys->isNotEmpty() && !$this->loadedAll) {
            $this->cache = $this->cache->merge(
                $this->toCollection(
                    $this
                        ->repository
                        ->getItems($missingKeys->toArray())
                )
                ->map(function ($serializedValue) {
                    return $this->deserializer->deserialize($serializedValue);
                })
            );
        }

        $keys->diff($this->getCachedKeys())->each(function ($key) {
            $this->cache[$key] = null;
        });

        return $this->cache->only($keys);
    }

    public function save() : void
    {
        $items = $this->cache->map(function ($deserializedValue) {
            return $this->serializer->serialize($deserializedValue);
        });

        if ($items->isNotEmpty()) {
            if ($items->count() > 1) {
                $this->repository->setItems($items->toArray());
            } else {
                $this->repository->setItem($items->keys()->first(), $items->first());
            }
        }
    }

    protected function getCachedKeys(): Collection
    {
        return $this->cache->keys();
    }

    protected function isCached(string $key): bool
    {
        return $this->cache->has($key);
    }

    protected function toCollection($mixed): Collection
    {
        return ($mixed instanceof Collection)
            ? $mixed
            : new Collection($mixed);
    }

    public function refresh(): self
    {
        $this->cache = null;
        $this->loadedAll = false;

        $this->initializeCache();

        return $this;
    }

    public function deleteAll() : void
    {
        $this->cache = new Collection;
        $this->loadedAll = true;
        $this->repository->deleteAll();
    }
}
