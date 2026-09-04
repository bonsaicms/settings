<?php

namespace BonsaiCms\Settings\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;
use BonsaiCms\Settings\Models\Setting;

class DatabaseSettingsRepository implements SettingsRepository
{
    /**
     * @var class-string<Setting>
     */
    protected string $model;

    protected ?string $connection;

    protected ?string $table;

    /**
     * The connection and table come from the driver configuration rather than
     * from the model, so two database drivers can point at two different
     * tables without needing two model classes.
     *
     * A driver may name a model of its own, as long as it extends Setting -
     * the "key" and "value" columns are what this repository speaks.
     *
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->model = $config['model'] ?? Setting::class;
        $this->connection = $config['connection'] ?? null;
        $this->table = $config['table'] ?? null;
    }

    public function setItem(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->query()->whereKey($key)->delete();
        } else {
            $this->query()->updateOrCreate(
                ['key' => $key],
                ['key' => $key, 'value' => $value]
            );
        }
    }

    public function setItems(array $items): void
    {
        $this->newModel()->getConnection()->transaction(function () use ($items) {
            $keysToDelete = [];
            $rowsToUpsert = [];

            foreach ($items as $key => $value) {
                if ($value === null) {
                    $keysToDelete[] = (string) $key;
                } else {
                    $rowsToUpsert[] = ['key' => (string) $key, 'value' => $value];
                }
            }

            if ($keysToDelete !== []) {
                $this->query()->whereIn('key', $keysToDelete)->delete();
            }

            if ($rowsToUpsert !== []) {
                $this->query()->upsert($rowsToUpsert, 'key');
            }
        });
    }

    public function getItem(string $key): ?string
    {
        return $this->query()->find($key)?->value;
    }

    public function getItems(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $items = $this->query()
            ->whereIn('key', $keys)
            ->get()
            ->mapWithKeys(fn ($item) => [$item->key => $item->value]);

        return (new Collection($keys))
            ->mapWithKeys(fn ($key) => [$key => $items->get($key)])
            ->all();
    }

    public function getAll(): array
    {
        return $this->query()->pluck('value', 'key')->all();
    }

    public function deleteAll(): void
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
     * A fresh model bound to this driver connection and table.
     */
    protected function newModel(): Setting
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

    /**
     * @return Builder<Setting>
     */
    protected function query(): Builder
    {
        return $this->newModel()->newQuery();
    }
}
