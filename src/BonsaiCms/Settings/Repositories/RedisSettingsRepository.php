<?php

namespace BonsaiCms\Settings\Repositories;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;

/**
 * Keeps every setting as a field of a single Redis hash, which is what makes
 * the whole SettingsRepository contract a one-command operation: HGETALL for
 * getAll(), HMGET for getItems(), DEL for deleteAll(). No index of keys has
 * to be maintained on the side, and a missing field is naturally absent -
 * the same meaning null carries everywhere else in this package.
 */
class RedisSettingsRepository implements SettingsRepository
{
    protected ?string $connection;

    protected string $key;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected readonly RedisFactory $redis,
        array $config = []
    ) {
        $this->connection = $config['connection'] ?? null;
        $this->key = $config['key'] ?? 'bonsaicms_settings';
    }

    public function setItem(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->connection()->hdel($this->key, $key);
        } else {
            $this->connection()->hset($this->key, $key, $value);
        }
    }

    public function setItems(array $items): void
    {
        $keysToDelete = [];
        $itemsToSet = [];

        foreach ($items as $key => $value) {
            if ($value === null) {
                $keysToDelete[] = (string) $key;
            } else {
                $itemsToSet[(string) $key] = $value;
            }
        }

        if ($keysToDelete !== []) {
            $this->connection()->hdel($this->key, ...$keysToDelete);
        }

        if ($itemsToSet !== []) {
            $this->connection()->hmset($this->key, $itemsToSet);
        }
    }

    public function getItem(string $key): ?string
    {
        return $this->normalize(
            $this->connection()->hget($this->key, $key)
        );
    }

    public function getItems(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        /*
         * Deduplicated first, and not only to save Redis the work: phpredis
         * answers HMGET with an array keyed by field, which Laravel turns
         * back into a positional list - so a field asked for twice collapses
         * into one value there and every position after it slides, reading as
         * null. predis keeps the repeat. Asking once is the only shape both
         * clients answer the same way.
         */
        $keys = array_values(array_unique(array_values($keys)));

        /*
         * Laravel normalises HMGET across phpredis and predis to a positional
         * list, so the results line up with the requested keys. phpredis
         * reports a missing field as false, predis as null.
         */
        $values = $this->connection()->hmget($this->key, $keys);

        return (new Collection($keys))
            ->mapWithKeys(fn ($key, $index) => [$key => $this->normalize($values[$index] ?? null)])
            ->all();
    }

    public function getAll(): array
    {
        return (new Collection($this->connection()->hgetall($this->key)))
            ->map(fn ($value) => $this->normalize($value))
            ->filter(fn ($value) => $value !== null)
            ->all();
    }

    public function deleteAll(): void
    {
        $this->connection()->del($this->key);
    }

    /**
     * Left without a native return type on purpose: Laravel itself only
     * documents what its Redis factory answers with, and the class behind it
     * is an abstract one whose two client subclasses answer through __call.
     *
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function connection()
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * phpredis answers with false for a field that is not in the hash; the
     * rest of the package expects null for "absent".
     */
    protected function normalize(mixed $value): ?string
    {
        return $value === false ? null : $value;
    }
}
