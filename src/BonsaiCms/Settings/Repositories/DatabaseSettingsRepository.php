<?php

namespace BonsaiCms\Settings\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;
use BonsaiCms\Settings\Models\Setting;

class DatabaseSettingsRepository implements SettingsRepository
{
    protected $model;

    protected $connection;

    protected $table;

    /**
     * The connection and table come from the driver configuration rather than
     * from the model, so two database drivers can point at two different
     * tables without needing two model classes.
     */
    public function __construct(array $config = [])
    {
        $this->model = $config['model'] ?? Setting::class;
        $this->connection = $config['connection'] ?? null;
        $this->table = $config['table'] ?? null;
    }

    public function setItem(string $key, $value): void
    {
        if ($value === null) {
            $this->query()->whereKey($key)->delete();
        } else {
            $this->query()->updateOrCreate(
                [
                    'key' => $key,
                ],
                [
                    'key' => $key,
                    'value' => $value,
                ]
            );
        }
    }

    public function setItems(array $items): void
    {
        $this->newModel()->getConnection()->transaction(function () use ($items) {
            list($itemsToDelete, $itemsToUpsert) = (new Collection($items))
                ->partition(function ($value, $key) {
                    return ($value === null);
                });

            // Delete items with null value
            if ($itemsToDelete->isNotEmpty()) {
                $this->query()->whereIn('key', $itemsToDelete->keys()->toArray())->delete();
            }

            // Upsert items with non-null values
            if ($itemsToUpsert->isNotEmpty()) {
                $this->query()->upsert(
                    $itemsToUpsert->map(function ($value, $key) {
                        return [
                            'key' => $key,
                            'value' => $value,
                        ];
                    })->values()->toArray(),
                    'key'
                );
            }
        });
    }

    public function getItem(string $key)
    {
        $item = $this->query()->find($key);

        return $item
            ? $item->value
            : null;
    }

    public function getItems(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $items = $this->query()->whereIn('key', $keys)->get()->mapWithKeys(function ($item) {
            return [$item->key => $item->value];
        });

        return (new Collection($keys))->mapWithKeys(function ($key) use ($items) {
            return [$key => $items->get($key)];
        })->toArray();
    }

    public function getAll(): array
    {
        return $this->query()->pluck('value', 'key')->toArray();
    }

    public function deleteAll() : void
    {
        /*
         * Deliberately not truncate(): on SQLite it also touches the
         * "sqlite_sequence" table, which does not exist until some table in
         * the database declares an AUTOINCREMENT column. Our settings table
         * never does.
         */
        $this->query()->delete();
    }

    /**
     * A fresh model bound to this driver's connection and table.
     */
    protected function newModel() : Model
    {
        $model = new $this->model;

        if ($this->connection !== null) {
            $model->setConnection($this->connection);
        }

        if ($this->table !== null) {
            $model->setTable($this->table);
        }

        return $model;
    }

    protected function query()
    {
        return $this->newModel()->newQuery();
    }
}
