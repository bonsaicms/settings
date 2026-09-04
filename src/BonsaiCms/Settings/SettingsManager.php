<?php

namespace BonsaiCms\Settings;

use Illuminate\Support\Collection;

/**
 * A write-behind cache in front of a SettingsRepository.
 *
 * Values are held deserialized in memory and serialized only in save(), which
 * writes back the keys that set() actually touched. Two pieces of state drive
 * nearly everything else: loadedAll, after which a cache miss means the key
 * genuinely does not exist, and the set of dirty keys, which is what save()
 * writes and what isDirty() answers from.
 */
class SettingsManager implements Contracts\SettingsManager
{
    /**
     * The deserialized values, plus a null for every key that turned out not
     * to exist - reading a miss twice is not worth a second query.
     *
     * @var Collection<string, mixed>
     */
    protected Collection $cache;

    /**
     * The keys set() has touched since the last save, as a map so a key
     * written twice is still written back once.
     *
     * @var array<string, true>
     */
    protected array $dirtyKeys = [];

    protected bool $loadedAll = false;

    protected Contracts\SettingsRepository $repository;

    protected Contracts\SettingsSerializer $serializer;

    protected Contracts\SettingsDeserializer $deserializer;

    public function __construct(
        Contracts\SettingsRepository $repository,
        Contracts\SettingsSerializer $serializer,
        Contracts\SettingsDeserializer $deserializer
    ) {
        $this->setRepository($repository);
        $this->setSerializer($serializer);
        $this->setDeserializer($deserializer);

        $this->cache = new Collection;
    }

    public function getRepository(): Contracts\SettingsRepository
    {
        return $this->repository;
    }

    public function setRepository(Contracts\SettingsRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function getSerializer(): Contracts\SettingsSerializer
    {
        return $this->serializer;
    }

    public function setSerializer(Contracts\SettingsSerializer $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function getDeserializer(): Contracts\SettingsDeserializer
    {
        return $this->deserializer;
    }

    public function setDeserializer(Contracts\SettingsDeserializer $deserializer): void
    {
        $this->deserializer = $deserializer;
    }

    public function set(string|array|Collection $keyOrPairs, mixed $value = null): void
    {
        if (is_string($keyOrPairs)) {
            $this->setOne($keyOrPairs, $value);

            return;
        }

        foreach ($keyOrPairs as $key => $pairValue) {
            $this->setOne((string) $key, $pairValue);
        }
    }

    public function get(string|array|Collection $keyOrKeys): mixed
    {
        return is_string($keyOrKeys)
            ? $this->getOne($keyOrKeys)
            : $this->getMany($keyOrKeys);
    }

    public function has(string $key): bool
    {
        return $this->getOne($key) !== null;
    }

    public function all(): Collection
    {
        if (! $this->loadedAll) {
            $this->cache = $this->toCollection($this->repository->getAll())
                ->diffKeys($this->getCachedKeys()->flip())
                ->map(fn ($serialized) => $this->deserialize($serialized))
                ->merge($this->cache);

            $this->loadedAll = true;
        }

        /*
         * A read of a key that does not exist leaves a null behind in the
         * cache, so the next read of it does not have to ask the repository
         * again. That negative cache is an implementation detail: null means
         * "absent" everywhere else in the package, so a key holding one is not
         * a setting and has no business showing up here.
         */
        return $this->cache->filter(fn ($value) => $value !== null);
    }

    public function save(): void
    {
        /*
         * Only what set() touched. Writing the whole cache back would rewrite
         * every setting after an all() - bumping rows nobody edited, letting
         * two concurrent requests undo each other's changes, and deleting keys
         * this request only ever read and found missing.
         */
        $items = $this->cache
            ->only(array_keys($this->dirtyKeys))
            ->map(fn ($deserialized) => $this->serialize($deserialized));

        if ($items->count() === 1) {
            $this->repository->setItem((string) $items->keys()->first(), $items->first());
        } elseif ($items->isNotEmpty()) {
            $this->repository->setItems($items->toArray());
        }

        $this->dirtyKeys = [];
    }

    public function refresh(): void
    {
        $this->cache = new Collection;
        $this->loadedAll = false;
        $this->dirtyKeys = [];
    }

    public function deleteAll(): void
    {
        $this->cache = new Collection;
        $this->loadedAll = true;
        $this->dirtyKeys = [];

        $this->repository->deleteAll();
    }

    public function isDirty(): bool
    {
        return $this->dirtyKeys !== [];
    }

    protected function setOne(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;

        $this->dirtyKeys[$key] = true;
    }

    protected function getOne(string $key): mixed
    {
        if (! $this->isCached($key) && ! $this->loadedAll) {
            $this->cache[$key] = $this->deserialize($this->repository->getItem($key));
        }

        return $this->cache->get($key);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $keys
     * @return Collection<string, mixed>
     */
    protected function getMany(array|Collection $keys): Collection
    {
        $keys = $this->toCollection($keys);

        // Asking the repository for the same key twice is only ever waste
        $missingKeys = $keys->diff($this->getCachedKeys())->unique()->values();

        if ($missingKeys->isNotEmpty() && ! $this->loadedAll) {
            $this->cache = $this->cache->merge(
                $this->toCollection($this->repository->getItems($missingKeys->all()))
                    ->map(fn ($serialized) => $this->deserialize($serialized))
            );
        }

        // Remember the misses too, so reading them again stays query free
        $keys->diff($this->getCachedKeys())->each(function ($key) {
            $this->cache[$key] = null;
        });

        /*
         * Keyed off the requested keys and not off the cache, so the result
         * comes back in the order it was asked for however much of it was
         * already in memory - the same guarantee every repository gives for
         * getItems().
         */
        return $keys->mapWithKeys(fn ($key) => [$key => $this->cache->get($key)]);
    }

    /**
     * Null never reaches the serializer: it means "absent", and deciding that
     * here rather than leaving it to the serializer is what stops a
     * replacement implementation from quietly storing a value for a delete.
     */
    protected function serialize(mixed $value): ?string
    {
        return $value === null
            ? null
            : $this->serializer->serialize($value);
    }

    protected function deserialize(?string $serialized): mixed
    {
        return $serialized === null
            ? null
            : $this->deserializer->deserialize($serialized);
    }

    /**
     * @return Collection<int, string>
     */
    protected function getCachedKeys(): Collection
    {
        return $this->cache->keys();
    }

    protected function isCached(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * @param  iterable<array-key, mixed>  $mixed
     * @return Collection<array-key, mixed>
     */
    protected function toCollection(iterable $mixed): Collection
    {
        return $mixed instanceof Collection
            ? $mixed
            : new Collection($mixed);
    }
}
