# bonsaicms/settings

[![tests](https://github.com/bonsaicms/settings/actions/workflows/tests.yml/badge.svg)](https://github.com/bonsaicms/settings/actions/workflows/tests.yml)

A settings manager for Laravel that can persist **any PHP value** — not just strings and numbers, but arrays, objects and [Eloquent](https://laravel.com/docs/eloquent) models.

Other settings packages (e.g. [anlutro/laravel-settings](https://github.com/anlutro/laravel-settings), [akaunting/setting](https://github.com/akaunting/setting)) store JSON-encodable scalars. This one serializes the value with PHP's `serialize()`, so whatever you put in is what you get out.

```php
Settings::set('theme', 'dark');
Settings::set('limits', ['upload' => 10, 'daily' => 100]);
Settings::set('owner', User::first());   // an Eloquent model
Settings::save();

Settings::get('owner')->is($user);       // true — same model, re-fetched from the DB
```

## At a glance

| | |
|---|---|
| Requires | PHP `^8.3`, Laravel `^12.0` or `^13.0` |
| Install | `composer require bonsaicms/settings` |
| Entry points | `Settings::` facade, `settings()` helper, `app(BonsaiCms\Settings\Contracts\SettingsManager::class)` |
| Default storage | `bonsaicms_settings` table, one row per key |
| On-disk format | `base64_encode(serialize($value))` in a `text` column |
| Write model | Write-behind — **nothing is persisted until `save()` is called** |
| License | MIT |

## Contents

- [Installation](#installation)
- [How it works](#how-it-works) — read this before using the package
- [API reference](#api-reference)
- [Storing Eloquent models](#storing-eloquent-models)
- [Storing your own objects](#storing-your-own-objects)
- [The settings() helper](#the-settings-helper)
- [Middleware](#middleware)
- [Artisan commands](#artisan-commands)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Gotchas](#gotchas)
- [Testing](#testing)

## Installation

```bash
composer require bonsaicms/settings
```

The service provider and the `Settings` facade are auto-discovered. Run the migration that creates the settings table:

```bash
php artisan migrate
```

Optionally publish the config file to `config/settings.php`:

```bash
php artisan vendor:publish --tag=settings
```

That is enough to start using the package. The two [middleware](#middleware) below are optional but recommended for web applications.

## How it works

### The write-behind cache

`SettingsManager` is registered as a **singleton** and holds every setting you touch in an in-memory `Collection`, **deserialized**. Reads fill that cache; writes only change it.

```php
Settings::set('theme', 'dark');   // in memory only — the database is untouched
Settings::get('theme');           // 'dark'
// ... request ends here without save() → the change is lost
```

Call `save()` to flush:

```php
Settings::set('theme', 'dark');
Settings::save();                 // now it is in the database
```

Or register the [`SaveSettings` middleware](#middleware), which calls `save()` for you at the end of every request — but only when something actually changed (`isDirty()`).

Two flags drive the whole thing:

- **`isDirty()`** — becomes `true` on any `set()`, `false` again after `save()` or `refresh()`.
- **`all()` marks everything as loaded** — once you have called `all()` (directly or through the `LoadSettings` middleware), the manager knows the full key set, so every later `get()` / `has()` is answered from memory with **zero queries**. A cache miss then means the key genuinely does not exist.

`save()` writes back the **entire cache**, not just the keys you changed.

### null means "absent"

`null` is the sentinel for a missing setting throughout the package. There is no way to store a `null` *value*:

```php
Settings::get('never-set');       // null
Settings::has('never-set');       // false

Settings::set('theme', null);     // this is a delete
Settings::save();                 // the row is removed from the database
```

`has($key)` is literally `get($key) !== null`. Values like `false`, `0` and `''` are stored and reported normally — only `null` is special.

### Serialization

A value is stored as `base64_encode(serialize($value))` in a `text` column, and read back with `unserialize(base64_decode(...))`.

Objects implementing `BonsaiCms\Settings\Contracts\SerializationWrappable` get special treatment: instead of serializing the object graph, the package stores the class name plus a small primitive payload you define, and rebuilds the object on read. See [Storing Eloquent models](#storing-eloquent-models) and [Storing your own objects](#storing-your-own-objects).

> **Security note.** Reading a setting runs `unserialize()` on whatever is in the row. Treat the settings table as trusted storage: never let untrusted input write raw rows into it.

## API reference

Every method is available on the `Settings` facade, on `settings()`, and on the `SettingsManager` contract.

| Method | Description |
|---|---|
| `set(string $key, mixed $value): void` | Write one setting into the cache. `null` deletes. |
| `set(array $pairs): void` | Write many settings at once (`['a' => 1, 'b' => 2]`). |
| `get(string $key): mixed` | Read one setting, or `null` if it does not exist. |
| `get(array $keys): Collection` | Read many; the result always contains **every** requested key, missing ones as `null`. |
| `has(string $key): bool` | `get($key) !== null`. |
| `all(): Collection` | Every setting, keyed by name. Loads the full set from the repository once, then serves from memory. |
| `save(): void` | Persist the whole cache to the repository. |
| `deleteAll(): void` | Delete every setting. **Applied immediately**, no `save()` needed. |
| `refresh(): void` | Drop the in-memory cache, **discarding unsaved changes**. |
| `isDirty(): bool` | Whether anything has been `set()` since the last `save()`. |
| `getRepository()` / `setRepository()` | Swap the storage backend at runtime. |
| `getSerializer()` / `setSerializer()` | Swap the serializer at runtime. |
| `getDeserializer()` / `setDeserializer()` | Swap the deserializer at runtime. |

### Examples

```php
use BonsaiCms\Settings\SettingsFacade as Settings;

Settings::set('count', 1);
Settings::get('count');              // 1

Settings::set('ratio', 1.2);
Settings::get('ratio');              // 1.2

Settings::set('enabled', true);
Settings::get('enabled');            // true

Settings::has('enabled');            // true
Settings::has('missing');            // false

// Many at once
Settings::set([
    'a' => 'A',
    'b' => 'B',
    'c' => 'C',
]);

Settings::get(['a', 'b', 'x']);
// Collection(['a' => 'A', 'b' => 'B', 'x' => null])

Settings::all();
// Collection(['a' => 'A', 'b' => 'B', 'c' => 'C', ...])

Settings::save();
```

## Storing Eloquent models

Add the `SerializableModelTrait` to the model and declare the `SerializationWrappable` interface:

```php
use Illuminate\Database\Eloquent\Model;
use BonsaiCms\Settings\Contracts\SerializationWrappable;
use BonsaiCms\Settings\Models\SerializableModelTrait;

class MyModel extends Model implements SerializationWrappable
{
    use SerializableModelTrait;
}
```

Then:

```php
$model = MyModel::first();

Settings::set('model', $model);
Settings::save();

// Same request, or any later one:
Settings::get('model')->is($model);   // true
```

**Model attributes are never serialized.** The trait stores only the model class and its primary key, and calls `MyModel::find($key)` when the setting is read. Consequences:

- You always read back the *current* state of the row, not a snapshot from when it was saved.
- If the model is deleted from the database, `Settings::get('model')` returns `null` (and therefore `has()` returns `false`).
- The stored value stays tiny, whatever the size of the model.

If you need different behaviour, implement `SerializationWrappable` yourself instead of using the trait.

## Storing your own objects

Any class can implement `BonsaiCms\Settings\Contracts\SerializationWrappable`:

```php
interface SerializationWrappable
{
    static function wrapBeforeSerialization($wrappable);

    static function unwrapAfterSerialization($wrappedClass, $wrappedValue);
}
```

- `wrapBeforeSerialization($object)` receives the object and returns a **primitive payload** (string, number, array …) that describes it. This payload is what gets serialized.
- `unwrapAfterSerialization($class, $payload)` receives the class name and that payload back, and returns the reconstructed object.

```php
use BonsaiCms\Settings\Contracts\SerializationWrappable;

class Money implements SerializationWrappable
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    static function wrapBeforeSerialization($wrappable)
    {
        return [
            'amount' => $wrappable->amount,
            'currency' => $wrappable->currency,
        ];
    }

    static function unwrapAfterSerialization($wrappedClass, $wrappedValue)
    {
        return new Money($wrappedValue['amount'], $wrappedValue['currency']);
    }
}
```

```php
Settings::set('price', new Money(1000, 'EUR'));
Settings::save();

Settings::get('price');   // Money(1000, 'EUR') — a new instance with the same state
```

Objects that do **not** implement the interface are still supported — they just go through plain `serialize()`, which stores the whole object graph.

## The settings() helper

`settings()` is a thin wrapper around the same singleton. It overloads on the shape of its arguments:

| Call | Equivalent to |
|---|---|
| `settings()` | the `SettingsManager` instance |
| `settings('a')` | `Settings::get('a')` |
| `settings(['a', 'b'])` | `Settings::get(['a', 'b'])` — **list** → multi-get |
| `settings(['a' => 'A'])` | `Settings::set(['a' => 'A'])` — **map** → multi-set |
| `settings('a', 'A')` | `Settings::set('a', 'A')` |
| `settings()->has('a')` | `Settings::has('a')` |
| `settings()->save()` | `Settings::save()` |

The get/set distinction for a single array argument is decided by its keys: sequential integer keys (`0, 1, 2, …`) mean *get these keys*, anything else means *set these pairs*.

> The helper is `require_once`d from the service provider's `boot()` method, not from composer's `files` autoload — it does not exist until the provider has booted.

## Middleware

Both middleware are optional. Register them in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append([
        \BonsaiCms\Settings\Http\Middleware\LoadSettings::class,
        \BonsaiCms\Settings\Http\Middleware\SaveSettings::class,
    ]);
})
```

- **`LoadSettings`** calls `all()` before the request is handled. One query up front, and every `get()` / `has()` during the request is then query-free.
- **`SaveSettings`** calls `save()` after the response is generated, but only if `isDirty()` — so read-only requests cost no writes.

## Artisan commands

```bash
php artisan settings:delete-all
```

Calls `Settings::deleteAll()`.

## Configuration

`config/settings.php` (publish it with `--tag=settings`):

### Swapping implementations

```php
'implementations' => [
    BonsaiCms\Settings\Contracts\SettingsManager::class      => BonsaiCms\Settings\SettingsManager::class,
    BonsaiCms\Settings\Contracts\SettingsSerializer::class   => BonsaiCms\Settings\SettingsSerializer::class,
    BonsaiCms\Settings\Contracts\SettingsDeserializer::class => BonsaiCms\Settings\SettingsDeserializer::class,
    BonsaiCms\Settings\Contracts\SettingsRepository::class   => BonsaiCms\Settings\Repositories\DatabaseSettingsRepository::class,
],
```

Every seam in the package is an interface bound from this array at `register()` time, so replacing any piece is a one-line config change.

Two repositories ship with the package:

| Repository | Use |
|---|---|
| `BonsaiCms\Settings\Repositories\DatabaseSettingsRepository` | Default. One row per setting. |
| `BonsaiCms\Settings\Repositories\ArraySettingsRepository` | In-memory only. **Debugging and tests** — it does not survive the request. |

### Database

```php
'database' => [
    'connection' => null,                  // null = the application's default connection
    'table' => 'bonsaicms_settings',
    'model' => \BonsaiCms\Settings\Models\Setting::class,
],
```

The model resolves its connection and table from this config *at call time*, so changing either takes effect without touching the model. The migration creates the table on the configured connection too.

Schema: `key` — `varchar(255)`, primary key; `value` — `text`, not null; plus `created_at` / `updated_at`.

### Exceptions

```php
'throwExceptions' => [
    'serialize' => env('APP_DEBUG'),
    'deserialize' => env('APP_DEBUG'),
],
```

By default (in production, with `APP_DEBUG=false`) a serialization failure is swallowed and the value becomes `null`. Turn these on to get a `SerializeException` / `DeserializeException` instead.

## Architecture

Four contracts in `src/BonsaiCms/Settings/Contracts/` are the extension points. Nothing is instantiated directly across a layer boundary — everything is resolved from the container.

| Contract | Responsibility | Default implementation |
|---|---|---|
| `SettingsManager` | The public API and the in-memory cache. Bound as a **singleton** — the only stateful piece. | `SettingsManager` |
| `SettingsRepository` | Persistence. Works purely in serialized **strings** — it never sees a PHP value or a `Setting` model. | `DatabaseSettingsRepository` |
| `SettingsSerializer` | PHP value → string | `SettingsSerializer` |
| `SettingsDeserializer` | string → PHP value | `SettingsDeserializer` |

`SerializationWrappable` is a fifth, user-facing interface — it is implemented by *your* classes, not by the package's internals.

```
src/BonsaiCms/Settings/
├── Commands/DeleteAllSettingsCommand.php   php artisan settings:delete-all
├── Contracts/                              the four seams + SerializationWrappable
├── Exceptions/                             SerializeException, DeserializeException, …
├── Http/Middleware/                        LoadSettings, SaveSettings
├── Models/
│   ├── Setting.php                         the Eloquent model behind the default repository
│   └── SerializableModelTrait.php          makes your models storable by identity
├── Repositories/                           DatabaseSettingsRepository, ArraySettingsRepository
├── SerializationWrapper.php                class name + primitive payload envelope
├── SettingsManager.php                     the cache, the dirty flag, the API
├── SettingsSerializer.php                  serialize() + base64_encode()
├── SettingsDeserializer.php                base64_decode() + unserialize()
├── SettingsFacade.php                      the Settings facade
└── SettingsServiceProvider.php             config-driven bindings, migration, helper, command
config/settings.php                         implementations, database, throwExceptions
database/migrations/                        creates the settings table
helpers/helpers.php                         the settings() helper
```

## Gotchas

Behaviours that are easy to get wrong — worth reading whether you are writing this code by hand or generating it:

1. **`set()` does not write to the database.** Only `save()` (or the `SaveSettings` middleware) does. Code that sets a value in an Artisan command or a queued job and never calls `save()` silently loses it.
2. **`deleteAll()` is the exception** — it hits the repository immediately and does not wait for `save()`.
3. **You cannot store `null`.** Setting a key to `null` deletes it; reading a missing key gives `null`. Use a sentinel of your own if you need to distinguish "absent" from "explicitly empty".
4. **`refresh()` throws away unsaved changes.** It resets the cache and clears the dirty flag.
5. **`save()` writes the whole cache**, so after `all()` + `save()` every row is rewritten (and its `updated_at` bumped), not just the ones you changed.
6. **`get(array $keys)` never omits keys.** Missing ones come back as `null`, so the result count always matches the request.
7. **Serialization errors are silent in production.** With `APP_DEBUG=false` a failed serialize/deserialize yields `null` rather than an exception — see [Exceptions](#exceptions).
8. **The stored format is PHP-specific.** `serialize()` output is not readable by other languages, and changing the serializer breaks settings already stored in the wild.
9. **The manager is a singleton**, so its cache is shared for the whole request/process lifetime — including long-running workers (Octane, queue workers), where you may want `refresh()` between jobs.

## Testing

```bash
composer install
composer test
```

The suite runs on [Pest](https://pestphp.com) with `orchestra/testbench` simulating the host application, against an in-memory SQLite database. `tests/Unit` tests the manager against mocked collaborators; `tests/Feature` boots the service provider and exercises real persistence — including running the whole `SettingsRepository` contract against both repository implementations.

CI runs `composer test` on every push, across PHP 8.3 / 8.4 and Laravel 12 / 13.

## Related packages

Need to read and write settings over HTTP? See [bonsaicms/settings-api](https://github.com/bonsaicms/settings-api).

## License

MIT.
