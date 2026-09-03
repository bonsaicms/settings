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
    protected $redis;

    protected $connection;

    protected $key;

    public function __construct(RedisFactory $redis, array $config = [])
    {
        $this->redis = $redis;
        $this->connection = $config['connection'] ?? null;
        $this->key = $config['key'] ?? 'bonsaicms_settings';
    }

    public function setItem(string $key, $value) : void
    {
        if ($value === null) {
            $this->connection()->hdel($this->key, $key);
        } else {
            $this->connection()->hset($this->key, $key, $value);
        }
    }

    public function setItems(array $items) : void
    {
        list($itemsToDelete, $itemsToSet) = (new Collection($items))
            ->partition(function ($value, $key) {
                return ($value === null);
            });

        if ($itemsToDelete->isNotEmpty()) {
            $this->connection()->hdel($this->key, ...$itemsToDelete->keys()->toArray());
        }

        if ($itemsToSet->isNotEmpty()) {
            $this->connection()->hmset($this->key, $itemsToSet->toArray());
        }
    }

    public function getItem(string $key)
    {
        return $this->normalize(
            $this->connection()->hget($this->key, $key)
        );
    }

    public function getItems(array $keys) : array
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
            ->mapWithKeys(function ($key, $index) use ($values) {
                return [$key => $this->normalize($values[$index] ?? null)];
            })
            ->toArray();
    }

    public function getAll() : array
    {
        return (new Collection($this->connection()->hgetall($this->key)))
            ->map(function ($value) {
                return $this->normalize($value);
            })
            ->toArray();
    }

    public function deleteAll() : void
    {
        $this->connection()->del($this->key);
    }

    protected function connection()
    {
        return $this->redis->connection($this->connection);
    }

    /**
     * phpredis answers with false for a field that is not in the hash; the
     * rest of the package expects null for "absent".
     */
    protected function normalize($value)
    {
        return ($value === false) ? null : $value;
    }
}
