<?php

namespace BonsaiCms\Settings\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;

class DatabaseSettingsRepository implements SettingsRepository
{
    protected $model;

    public function __construct()
    {
        $this->model = config('settings.database.model');
    }

    public function setItem(string $key, $value): void
    {
        if ($value === null) {
            $this->model::whereKey($key)->delete();
        } else {
            $this->model::updateOrCreate(
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
        DB::transaction(function () use ($items) {
            list($itemsToDelete, $itemsToUpsert) = (new Collection($items))
                ->partition(function ($value, $key) {
                    return ($value === null);
                });

            // Delete items with null value
            $this->model::whereIn('key', $itemsToDelete->keys()->toArray())->delete();

            // Upsert items with non-null values
            $this->model::upsert(
                $itemsToUpsert->map(function ($value, $key) {
                    return [
                        'key' => $key,
                        'value' => $value,
                    ];
                })->values()->toArray(),
                'key'
            );
        });
    }

    public function getItem(string $key)
    {
        $item = $this->model::find($key);

        return $item
            ? $item->value
            : null;
    }

    public function getItems(array $keys): array
    {
        $items = $this->model::whereIn('key', $keys)->get()->mapWithKeys(function ($item) {
            return [$item->key => $item->value];
        });

        return (new Collection($keys))->mapWithKeys(function ($key) use ($items) {
            return [$key => $items->get($key)];
        })->toArray();
    }

    public function getAll(): array
    {
        return $this->model::pluck('value', 'key')->toArray();
    }

    public function deleteAll() : void
    {
        /*
         * Deliberately not truncate(): on SQLite it also touches the
         * "sqlite_sequence" table, which does not exist until some table in
         * the database declares an AUTOINCREMENT column. Our settings table
         * never does.
         */
        $this->model::query()->delete();
    }
}
