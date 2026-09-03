<?php

namespace BonsaiCms\Settings\Repositories;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use BonsaiCms\Settings\Contracts\SettingsRepository;

/**
 * Keeps every setting in one JSON file, for applications that need settings
 * before (or without) a database - an installer, a maintenance switch, or the
 * database credentials themselves.
 *
 * Reads and writes take a lock, but a full read-modify-write cycle does not
 * hold one, so this driver is meant for a single application server. The
 * values are already serialized strings, so the file stays plain JSON and can
 * be inspected by hand.
 */
class FileSettingsRepository implements SettingsRepository
{
    protected $files;

    protected $path;

    public function __construct(Filesystem $files, array $config = [])
    {
        $this->files = $files;
        $this->path = $config['path'] ?? storage_path('app/bonsaicms_settings.json');
    }

    public function setItem(string $key, $value) : void
    {
        $this->setItems([$key => $value]);
    }

    public function setItems(array $items) : void
    {
        list($itemsToDelete, $itemsToSet) = (new Collection($items))
            ->partition(function ($value, $key) {
                return ($value === null);
            });

        $this->write(
            (new Collection($this->read()))
                ->merge($itemsToSet)
                ->except($itemsToDelete->keys()->toArray())
                ->toArray()
        );
    }

    public function getItem(string $key)
    {
        return $this->read()[$key] ?? null;
    }

    public function getItems(array $keys) : array
    {
        if ($keys === []) {
            return [];
        }

        $items = $this->read();

        return (new Collection($keys))->mapWithKeys(function ($key) use ($items) {
            return [$key => $items[$key] ?? null];
        })->toArray();
    }

    public function getAll() : array
    {
        return $this->read();
    }

    public function deleteAll() : void
    {
        if ($this->files->exists($this->path)) {
            $this->files->delete($this->path);
        }
    }

    /**
     * The path this driver reads and writes.
     */
    public function getPath() : string
    {
        return $this->path;
    }

    /**
     * A missing file means "no settings yet", which is the state on a fresh
     * install. Unreadable content is treated the same way rather than taking
     * the application down, matching how the serializers swallow failures.
     */
    protected function read() : array
    {
        if ( ! $this->files->exists($this->path)) {
            return [];
        }

        $contents = $this->files->get($this->path, true);

        if (trim((string) $contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded)
            ? $decoded
            : [];
    }

    protected function write(array $items) : void
    {
        // Nothing left to store, so do not leave an empty file behind
        if ($items === []) {
            $this->deleteAll();

            return;
        }

        $this->files->ensureDirectoryExists(dirname($this->path));

        $this->files->put(
            $this->path,
            json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            true
        );
    }
}
