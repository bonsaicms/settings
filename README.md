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
| Storage | Pluggable drivers: `database` (default), `redis`, `file`, `array` |
| Stored format | `base64_encode(serialize($value))` |
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

The service provider and the `Settings` facade are auto-discovered. Publish the migration, then run it:

```bash
php artisan vendor:publish --tag=settings-migrations
```

```bash
php artisan migrate
```

The migration is copied into your `database/migrations` with a fresh timestamp, so it belongs to your application — rename the table, change the column types or add an index before you run it. The package does **not** register it for you, so nothing is created behind your back. If you would rather not run every other pending migration in the application, point `migrate` at the published file (the publish command prints its full name, which starts with the timestamp it was stamped with):

```bash
php artisan migrate --path=database/migrations/YYYY_MM_DD_HHMMSS_bonsaicms_create_settings_table.php
```

Optionally publish the config file to `config/settings.php`:

```bash
php artisan vendor:publish --tag=settings-config
```

Use `--tag=settings` to publish both at once.

That is enough to start using the package. The two [middleware](#middleware) below are optional but recommended for web applications.

## How it works

### The write-behind cache

`SettingsManager` is registered as a **singleton** and holds every setting you touch in an in-memory `Collection`, **deserialized**. Reads fill that cache; writes only change it.

```php
Settings::set('theme', 'dark');   // in memory only — the store is untouched
Settings::get('theme');           // 'dark'
// ... request ends here without save() → the change is lost
```

Call `save()` to flush:

```php
Settings::set('theme', 'dark');
Settings::save();                 // now it is in the store
```

Or register the [`SaveSettings` middleware](#middleware), which calls `save()` for you at the end of every request — but only when something actually changed (`isDirty()`).

Two pieces of state drive the whole thing:

- **`isDirty()`** — true as soon as a `set()` has happened, false again after `save()` or `refresh()`. The manager remembers *which* keys were touched, not merely that something was.
- **`all()` marks everything as loaded** — once you have called `all()` (directly or through the `LoadSettings` middleware), the manager knows the full key set, so every later `get()` / `has()` is answered from memory with **zero queries**. A cache miss then means the key genuinely does not exist.

`save()` writes back **only the keys you changed**, never the whole cache.

### null means "absent"

`null` is the sentinel for a missing setting throughout the package. There is no way to store a `null` *value*:

```php
Settings::get('never-set');       // null
Settings::has('never-set');       // false

Settings::set('theme', null);     // this is a delete
Settings::save();                 // the entry is removed from the store
```

`has($key)` is literally `get($key) !== null`. Values like `false`, `0` and `''` are stored and reported normally — only `null` is special.

### Serialization

A value is stored as `base64_encode(serialize($value))` — in a `text` column, a Redis hash field or a JSON key, depending on the driver — and read back with `unserialize(base64_decode(...))`.

Objects implementing `BonsaiCms\Settings\Contracts\SerializationWrappable` get special treatment: instead of serializing the object graph, the package stores the class name plus a small primitive payload you define, and rebuilds the object on read. See [Storing Eloquent models](#storing-eloquent-models) and [Storing your own objects](#storing-your-own-objects).

> **Security note.** Reading a setting runs `unserialize()` on whatever the driver hands back. Treat the settings store as trusted storage — the table, the Redis hash or the JSON file — and never let untrusted input write raw entries into it.

## API reference

Every method is available on the `Settings` facade, on `settings()`, and on the `SettingsManager` contract.

| Method | Description |
|---|---|
| `set(string $key, mixed $value): void` | Write one setting into the cache. `null` deletes. |
| `set(array $pairs): void` | Write many settings at once (`['a' => 1, 'b' => 2]`). |
| `get(string $key): mixed` | Read one setting, or `null` if it does not exist. |
| `get(array $keys): Collection` | Read many; the result always contains **every** requested key, in the order asked for, missing ones as `null`. |
| `has(string $key): bool` | `get($key) !== null`. |
| `all(): Collection` | Every setting, keyed by name. Loads the full set from the repository once, then serves from memory. |
| `save(): void` | Persist everything `set()` has touched since the last save. |
| `deleteAll(): void` | Delete every setting. **Applied immediately**, no `save()` needed. |
| `refresh(): void` | Drop the in-memory cache, **discarding unsaved changes**. |
| `isDirty(): bool` | Whether anything has been `set()` since the last `save()`. |
| `getRepository()` / `setRepository()` | Swap the storage backend at runtime. |
| `getSerializer()` / `setSerializer()` | Swap the serializer at runtime. |
| `getDeserializer()` / `setDeserializer()` | Swap the deserializer at runtime. |

Anywhere an `array` is shown above, an `Illuminate\Support\Collection` works too.

### Examples

```php
use BonsaiCms\Settings\Facades\Settings;

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

Add the `SerializableModel` trait to the model and declare the `SerializationWrappable` interface:

```php
use Illuminate\Database\Eloquent\Model;
use BonsaiCms\Settings\Concerns\SerializableModel;
use BonsaiCms\Settings\Contracts\SerializationWrappable;

class MyModel extends Model implements SerializationWrappable
{
    use SerializableModel;
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
    public function wrapBeforeSerialization(): mixed;

    public static function unwrapAfterSerialization(mixed $wrappedValue): mixed;
}
```

- `wrapBeforeSerialization()` returns a **primitive payload** (string, number, array …) describing the object. This payload is what gets serialized, alongside the class name.
- `unwrapAfterSerialization($payload)` receives that payload back on the class it was stored for, and returns the reconstructed object.

```php
use BonsaiCms\Settings\Contracts\SerializationWrappable;

class Money implements SerializationWrappable
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function wrapBeforeSerialization(): mixed
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public static function unwrapAfterSerialization(mixed $wrappedValue): mixed
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

> The helper is registered through composer's `files` autoload, so it exists before any service provider boots. It still resolves the manager out of the container at call time, so calling it before the provider has registered fails on the binding, not on the function.

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

Calls `Settings::deleteAll()` on the default driver.

```bash
php artisan settings:delete-all --driver=redis
```

Empties one named [driver](#drivers) instead, leaving the others alone.

## Configuration

`config/settings.php` (publish it with `--tag=settings-config`):

### Drivers

Storage works the way Laravel's cache stores do: `default` names one of the `drivers`, and each driver has a `driver` **type** plus its own settings.

```php
'default' => env('SETTINGS_DRIVER', 'database'),

'drivers' => [
    'database' => [
        'driver' => 'database',
        'connection' => env('SETTINGS_DATABASE_CONNECTION'),   // null = the app's default connection
        'table' => env('SETTINGS_DATABASE_TABLE', 'bonsaicms_settings'),
        'model' => BonsaiCms\Settings\Models\Setting::class,
    ],

    'redis' => [
        'driver' => 'redis',
        'connection' => env('SETTINGS_REDIS_CONNECTION', 'default'),
        'key' => env('SETTINGS_REDIS_KEY', 'bonsaicms_settings'),
    ],

    'file' => [
        'driver' => 'file',
        'path' => env('SETTINGS_FILE_PATH', storage_path('app/bonsaicms_settings.json')),
    ],

    'array' => [
        'driver' => 'array',
    ],
],
```

Switching backend is one environment variable:

```bash
SETTINGS_DRIVER=redis
```

| Type | Stores | Use |
|---|---|---|
| `database` | One row per setting. | **Default.** Needs the published migration. |
| `redis` | One Redis hash, one field per setting. | Several application servers, or keeping settings off the database. Needs `predis/predis` or `ext-redis`. |
| `file` | One JSON file. | Settings needed before (or without) a database — an installer, a maintenance switch. Single server. |
| `array` | Memory. | **Debugging and tests** — it does not survive the request. |

The names are yours, and two drivers may share a type. That is how you keep two sets of settings apart:

```php
'drivers' => [
    'tenant_one' => ['driver' => 'redis', 'connection' => 'default', 'key' => 'settings_tenant_one'],
    'tenant_two' => ['driver' => 'redis', 'connection' => 'default', 'key' => 'settings_tenant_two'],
],
```

```php
use BonsaiCms\Settings\Contracts\SettingsRepositoryFactory;

$repository = app(SettingsRepositoryFactory::class)->driver('tenant_two');

// Point the manager at it — note this shares the manager's cache, so refresh() first
Settings::refresh();
Settings::setRepository($repository);
```

`php artisan settings:delete-all --driver=tenant_two` empties one driver without touching the others.

To add a driver of your own, implement `Contracts\SettingsRepository` with an `array $config` constructor and register its type:

```php
'driver_implementations' => [
    // …the four above, plus:
    'dynamodb' => App\Settings\DynamoDbSettingsRepository::class,
],
```

### The database table

Only the `database` driver needs a table. `migrations.driver` names the driver the published migration belongs to, so the migration follows that driver's `connection` and `table`:

```php
'migrations' => [
    'driver' => env('SETTINGS_MIGRATION_DRIVER', 'database'),
],
```

Schema: `key` — `varchar(255)`, primary key; `value` — `text`, not null; plus `created_at` / `updated_at`.

### Swapping implementations

```php
'bindings' => [
    BonsaiCms\Settings\Contracts\SettingsManager::class           => BonsaiCms\Settings\SettingsManager::class,
    BonsaiCms\Settings\Contracts\SettingsSerializer::class        => BonsaiCms\Settings\SettingsSerializer::class,
    BonsaiCms\Settings\Contracts\SettingsDeserializer::class      => BonsaiCms\Settings\SettingsDeserializer::class,
    BonsaiCms\Settings\Contracts\SettingsRepositoryFactory::class => BonsaiCms\Settings\SettingsRepositoryFactory::class,
],
```

Every seam in the package is an interface bound to the class named here, read when the binding is resolved, so replacing any piece is a one-line config change. `SettingsRepository` is not in the list: it is bound to whichever driver `default` names.

Not to be confused with `driver_implementations`, which maps a *storage type* to a repository class.

### Exceptions

```php
'throwExceptions' => [
    'serialize' => env('APP_DEBUG'),
    'deserialize' => env('APP_DEBUG'),
],
```

By default (in production, with `APP_DEBUG=false`) a serialization failure is swallowed and the value becomes `null`. Turn these on to get a `SerializeException` / `DeserializeException` instead.

## Architecture

Five contracts in `src/BonsaiCms/Settings/Contracts/` are the extension points. Nothing is instantiated directly across a layer boundary — everything is resolved from the container.

| Contract | Responsibility | Default implementation |
|---|---|---|
| `SettingsManager` | The public API and the in-memory cache. Bound as a **singleton** — the only stateful piece. | `SettingsManager` |
| `SettingsRepositoryFactory` | Turns a driver name into a repository. Bound as a **singleton**, and caches one instance per name. | `SettingsRepositoryFactory` |
| `SettingsRepository` | Persistence. Works purely in serialized **strings** — it never sees a PHP value or a `Setting` model. | whichever driver `settings.default` names |
| `SettingsSerializer` | PHP value → string | `SettingsSerializer` |
| `SettingsDeserializer` | string → PHP value | `SettingsDeserializer` |

`SerializationWrappable` is a sixth, user-facing interface — it is implemented by *your* classes, not by the package's internals.

```
src/BonsaiCms/Settings/
├── Commands/DeleteAllSettingsCommand.php   php artisan settings:delete-all
├── Concerns/SerializableModel.php          makes your models storable by identity
├── Contracts/                              the five seams + SerializationWrappable
├── Exceptions/                             SettingsException and its three subclasses
├── Facades/Settings.php                    the Settings facade
├── Http/Middleware/                        LoadSettings, SaveSettings
├── Models/Setting.php                      the Eloquent model behind the database driver
├── Repositories/                           Database, Redis, File and Array repositories
├── SerializationWrapper.php                class name + primitive payload envelope
├── SettingsManager.php                     the cache, the dirty keys, the API
├── SettingsRepositoryFactory.php           driver name → repository
├── SettingsSerializer.php                  serialize() + base64_encode()
├── SettingsDeserializer.php                base64_decode() + unserialize()
└── SettingsServiceProvider.php             config-driven bindings, publishing, command
config/settings.php                         drivers, bindings, throwExceptions
database/migrations/                        publishable migration for the settings table
helpers/helpers.php                         the settings() helper, composer files autoload
```

## Gotchas

Behaviours that are easy to get wrong — worth reading whether you are writing this code by hand or generating it:

1. **`set()` does not write to the database.** Only `save()` (or the `SaveSettings` middleware) does. Code that sets a value in an Artisan command or a queued job and never calls `save()` silently loses it.
2. **`deleteAll()` is the exception** — it hits the repository immediately and does not wait for `save()`.
3. **You cannot store `null`.** Setting a key to `null` deletes it; reading a missing key gives `null`. Use a sentinel of your own if you need to distinguish "absent" from "explicitly empty".
4. **`refresh()` throws away unsaved changes.** It resets the cache and forgets which keys were dirty.
5. **`save()` writes only what you `set()`.** Keys that were merely read — including by `all()` — are left alone, so a request that changes one setting cannot undo another request's changes to the rest.
6. **`get(array $keys)` never omits keys.** Missing ones come back as `null`, so the result count always matches the request, and the order matches too.
7. **Serialization errors are silent in production.** With `APP_DEBUG=false` a failed serialize/deserialize yields `null` rather than an exception — see [Exceptions](#exceptions).
8. **The stored format is PHP-specific.** `serialize()` output is not readable by other languages, and changing the serializer breaks settings already stored in the wild.
9. **The manager is a singleton**, so its cache is shared for the whole request/process lifetime — including long-running workers (Octane, queue workers), where you may want `refresh()` between jobs.

## Testing

```bash
composer install
composer test
```

The suite runs on [Pest](https://pestphp.com) with `orchestra/testbench` simulating the host application, against an in-memory SQLite database. `tests/Unit` tests the manager against mocked collaborators; `tests/Feature` boots the service provider and exercises real persistence — including running the whole `SettingsRepository` contract against every driver.

Nothing in `tests/Feature` knows which driver it is running against, so the same suite can be pointed at any of them:

```bash
SETTINGS_DRIVER=redis composer test
```

The Redis tests **skip** when there is no Redis to talk to, so `composer test` works on a machine without one. Point `REDIS_HOST` / `REDIS_PORT` at a server to run them, and `REDIS_CLIENT` at `predis` or `phpredis` to pick the client. Likewise `DB_DRIVER` (with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) swaps SQLite for a real `pgsql`, `mariadb` or `mysql` server.

### Static analysis

```bash
composer analyse
```

PHPStan (through [larastan](https://github.com/larastan/larastan)) at **level 8**, over `src`, `config`, `database`, `helpers` and `tests/Mocks`. It runs as its own CI job, so a change is expected to keep it green.

### Continuous integration

CI runs on every push in four sets of jobs:

- **analyse** — `composer analyse`. It needs no service containers, so it is the first job to fail.
- **versions** — every supported PHP (8.3, 8.4) × Laravel (12, 13), on SQLite.
- **drivers** — the whole suite once per driver: `database`, `array`, `file` and `redis`, the last on both Redis clients.
- **databases** — the database driver against real PostgreSQL, MariaDB and MySQL, since the upsert SQL is not the same on every engine.

`SETTINGS_REQUIRE_REDIS=1` is set throughout, so a missing Redis fails the build instead of quietly skipping.

## Related packages

Need to read and write settings over HTTP? See [bonsaicms/settings-api](https://github.com/bonsaicms/settings-api).

## License

MIT.
