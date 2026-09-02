# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`bonsaicms/settings` — a standalone Laravel package (not an application) providing a settings manager that can persist *any* PHP value, including Eloquent models and arbitrary objects. There is no `app/`, no `artisan`; the host application is simulated by `orchestra/testbench` in tests.

Supports PHP `^8.3` and Laravel `^12.0|^13.0` — keep changes compatible with both Laravel lines (nothing that only exists in 13). Support for Laravel 8–11 and PHP 7.3–8.2 was dropped, so the next release is a major.

## Commands

```bash
composer test
```

```bash
php vendor/bin/pest
```

Run a subset by test-name substring:

```bash
php vendor/bin/pest --filter "round trips primitives"
```

`php` and `composer` come from Herd (`C:\Users\mspid\.config\herd\bin`) and are on PATH in PowerShell but **not** in the bundled bash shell — run test commands from PowerShell, prefixed with `php` (e.g. `php vendor/bin/pest`).

Run one suite with `php vendor/bin/pest --testsuite Unit` (or `Feature`); both are registered in [phpunit.xml](phpunit.xml), which is on the current PHPUnit schema. There is no lint or static-analysis tooling in this repo.

## Architecture

Four contracts in `src/BonsaiCms/Settings/Contracts/` are the seams; every implementation is swappable via [config/settings.php](config/settings.php) `implementations`, which [SettingsServiceProvider](src/BonsaiCms/Settings/SettingsServiceProvider.php) reads at `register()` time to bind them. **Add a new interface → add a binding + a config entry; never `new` an implementation across layer boundaries.**

- `SettingsManager` — bound as a **singleton**; the only stateful piece.
- `SettingsRepository` — persistence. `DatabaseSettingsRepository` (default) or `ArraySettingsRepository` (debug only, does not survive a request).
- `SettingsSerializer` / `SettingsDeserializer` — value ⇄ string.

### The write-behind cache in SettingsManager

[SettingsManager](src/BonsaiCms/Settings/SettingsManager.php) holds an in-memory `Collection` cache plus two flags that drive nearly all of its logic:

- `$loadedAll` — set by `all()`. Once true, `getOne`/`getMany` **never** hit the repository again; a cache miss means the key genuinely does not exist. This is why the `LoadSettings` middleware makes subsequent `get()` calls query-free.
- `$dirty` — set by any `set()`. `SaveSettings` middleware only calls `save()` when dirty.

Values are held **deserialized** in the cache and serialized only in `save()`, which writes the *entire* cache back (single `setItem` when one entry, `setItems`/upsert otherwise). Nothing is persisted until `save()` runs.

`null` is the "absent" sentinel throughout: `has()` is `get() !== null`, serializers short-circuit on null, and both repositories **drop** entries whose value is null rather than storing them. Keep that invariant when touching any of these.

Every repository method returns the raw serialized **string**, never a `Setting` model — the manager pipes whatever comes back straight into `deserialize()`.

### Serialization of objects

`serialize()` + `base64_encode()` into a `text` column. Objects implementing `Contracts\SerializationWrappable` are first wrapped in [SerializationWrapper](src/BonsaiCms/Settings/SerializationWrapper.php), which stores only the class name (`$c`) and a primitive payload (`$d`) — deliberately one-letter properties to keep the serialized string short. `unwrap()` calls the class's static `unwrapAfterSerialization()`. `Models\SerializableModelTrait` implements this for Eloquent by storing just the primary key and re-`find()`ing on read, so **model attributes are never serialized**.

Serializer/deserializer failures are swallowed and return `null` unless `settings.throwExceptions.*` is on (defaults to `env('APP_DEBUG')`).

### Registration details

- `helpers/helpers.php` is in composer's `files` autoload, so `settings()` exists before any provider boots. It was `require_once`d from the provider's `boot()` until the Laravel 12/13 major, which made it order-dependent against other packages. Keep the `function_exists` guard: it is what stops a colliding `settings()` from another package from fataling.
- The `settings()` helper overloads on argument shape: an array with sequential integer keys is a multi-get, an associative array is a multi-set. See [helpers/helpers.php](helpers/helpers.php).
- `Models\Setting` resolves its connection and table from config at call time (`getConnectionName()`/`getTable()`), so config changes take effect without touching the model.
- The migration is **not** registered with the application. It is published with `publishesMigrations()` under the `settings-migrations` tag (config under `settings-config`, both under `settings`), so the host app owns and can edit it. [tests/FeatureTestCase.php](tests/FeatureTestCase.php) therefore loads it itself in `defineDatabaseMigrations()` — feature tests get the table from there, not from the provider.

## Tests

Tests are written in **Pest**, in two suites bound to different base classes by [tests/Pest.php](tests/Pest.php):

- **`tests/Unit`** → `Tests\TestCase`, which extends Testbench but does **not** register `SettingsServiceProvider` and has no database. `ManagerTest` builds `SettingsManager` directly with Mockery mocks of the three collaborators (in `beforeEach`, exposed as `$this->manager` etc.) and asserts on the exact repository calls — so a change to *when* the manager calls the repository breaks it even if behaviour is externally identical. That is intentional; update the expectations deliberately.
- **`tests/Feature`** → `Tests\FeatureTestCase`, which registers the provider and the facade alias and runs against sqlite `:memory:` with `RefreshDatabase`. **Anything about repositories, the migration, or the manager's persistence belongs here** — the mocked unit tests cannot see a repository returning the wrong shape, which is precisely how `getItems()` shipped broken.

`SettingsRepositoryTest` runs the whole `SettingsRepository` contract against *both* implementations through the `repositories` dataset, so the array and database repositories cannot drift apart. Dataset entries are *argument lists* — an array value needs one extra level of wrapping, and object/repository values are closures so every test gets a fresh instance.

Serializer tests assert round-trips through `SettingsDeserializer`, not against fixed strings, so the on-disk format may change — but doing so breaks existing stored settings in the wild.
