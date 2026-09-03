<?php

use Illuminate\Filesystem\Filesystem;
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;
use BonsaiCms\Settings\Repositories\FileSettingsRepository;

/**
 * The behaviour every driver shares lives in SettingsRepositoryTest; what is
 * here is what only the file driver can get wrong - mostly the states its file
 * can be found in.
 */

beforeEach(function () {
    $this->files = new Filesystem;
    $this->repository = app(SettingsRepositoryFactory::class)->driver('file');
    $this->path = $this->repository->getPath();

    $this->repository->deleteAll();
});

afterEach(function () {
    $this->repository->deleteAll();
});

it('uses the path from the driver config', function () {
    config()->set('settings.drivers.elsewhere', [
        'driver' => 'file',
        'path' => dirname($this->path).DIRECTORY_SEPARATOR.'elsewhere.json',
    ]);

    $repository = app(SettingsRepositoryFactory::class)->driver('elsewhere');

    expect($repository->getPath())->toBe(dirname($this->path).DIRECTORY_SEPARATOR.'elsewhere.json');

    $repository->setItem('a', 'A-ser');

    expect($this->files->exists($repository->getPath()))->toBeTrue();

    $repository->deleteAll();
});

it('reads as empty when the file does not exist yet', function () {
    expect($this->files->exists($this->path))->toBeFalse();

    expect($this->repository->getAll())->toBe([]);
    expect($this->repository->getItem('a'))->toBeNull();
    expect($this->repository->getItems(['a', 'b']))->toEqual(['a' => null, 'b' => null]);
});

it('creates the file and any missing directory on the first write', function () {
    $this->files->deleteDirectory(dirname($this->path));

    expect($this->files->isDirectory(dirname($this->path)))->toBeFalse();

    $this->repository->setItem('a', 'A-ser');

    expect($this->files->exists($this->path))->toBeTrue();
    expect($this->repository->getItem('a'))->toBe('A-ser');
});

it('stores the settings as readable json', function () {
    $this->repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $contents = $this->files->get($this->path);

    expect(json_decode($contents, true))->toEqual([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    /*
     * Pretty printed and with slashes and unicode left alone, because the
     * point of this driver is a file an operator can open and read - an
     * installer's settings, or a maintenance switch.
     */
    expect($contents)->toContain("\n");
    expect($contents)->toContain('"a"');
});

it('is the whole state, as another process would find it', function () {
    /*
     * The repository holds nothing in memory: a second instance over the same
     * path - a queue worker, the next request - sees exactly what was written.
     */
    $this->repository->setItems(['a' => 'A-ser', 'b' => 'B-ser']);

    $other = new FileSettingsRepository(new Filesystem, ['path' => $this->path]);

    expect($other->getAll())->toEqual(['a' => 'A-ser', 'b' => 'B-ser']);

    $other->setItem('a', null);

    expect($this->repository->getAll())->toEqual(['b' => 'B-ser']);
});

it('keeps the file valid after deleting one of several settings', function () {
    $this->repository->setItems([
        'a' => 'A-ser',
        'b' => 'B-ser',
    ]);

    $this->repository->setItems(['a' => null]);

    expect(json_decode($this->files->get($this->path), true))->toEqual(['b' => 'B-ser']);
});

it('reads as empty when the file holds nothing', function () {
    $this->files->ensureDirectoryExists(dirname($this->path));
    $this->files->put($this->path, '');

    expect($this->repository->getAll())->toBe([]);
});

it('reads as empty rather than failing when the file is not valid json', function () {
    $this->files->ensureDirectoryExists(dirname($this->path));
    $this->files->put($this->path, '{ this is not json');

    /*
     * Taking the application down on a damaged settings file would be worse
     * than starting from nothing, and it matches how the serializers swallow
     * what they cannot read.
     */
    expect($this->repository->getAll())->toBe([]);
    expect($this->repository->getItem('a'))->toBeNull();

    // Writing recovers the file
    $this->repository->setItem('a', 'A-ser');

    expect($this->repository->getAll())->toEqual(['a' => 'A-ser']);
});

it('reads as empty when the file holds json that is not an object', function () {
    $this->files->ensureDirectoryExists(dirname($this->path));
    $this->files->put($this->path, '"just a string"');

    expect($this->repository->getAll())->toBe([]);
});

it('removes the file instead of leaving an empty one behind', function () {
    $this->repository->setItem('a', 'A-ser');

    expect($this->files->exists($this->path))->toBeTrue();

    $this->repository->setItem('a', null);

    expect($this->files->exists($this->path))->toBeFalse();
    expect($this->repository->getAll())->toBe([]);
});

it('removes the file on deleteAll', function () {
    $this->repository->setItem('a', 'A-ser');

    $this->repository->deleteAll();

    expect($this->files->exists($this->path))->toBeFalse();
});

it('falls back to the storage path when the driver names none', function () {
    $repository = new FileSettingsRepository(new Filesystem, ['driver' => 'file']);

    expect($repository->getPath())->toBe(storage_path('app/bonsaicms_settings.json'));
});
