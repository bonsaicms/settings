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

Five contracts in `src/BonsaiCms/Settings/Contracts/` are the seams; every implementation is swappable via [config/settings.php](config/settings.php) `implementations`, which [SettingsServiceProvider](src/BonsaiCms/Settings/SettingsServiceProvider.php) reads at `register()` time to bind them. **Add a new interface → add a binding + a config entry; never `new` an implementation across layer boundaries.**

- `SettingsManager` — bound as a **singleton**; the only stateful piece.
- `SettingsRepositoryFactory` — a **singleton**; turns a driver name into a repository.
- `SettingsRepository` — persistence. Not bound to a class: the provider binds it to a closure asking the factory for the *default* driver, so the backend is a runtime decision.
- `SettingsSerializer` / `SettingsDeserializer` — value ⇄ string.

### Drivers

Storage is configured the way Laravel configures cache stores: `settings.default` names one entry of `settings.drivers`, and each entry has a `driver` **type** plus that implementation's own config. The type maps to a class through `settings.driver_implementations`. Two entries may share a type and differ only in config — two Redis drivers on two hashes, two database drivers on two tables — which is why **repositories take their config as a constructor `array $config` and must never read `config()` themselves**.

| type | class | config keys |
|---|---|---|
| `database` | `DatabaseSettingsRepository` | `connection`, `table`, `model` |
| `redis` | `RedisSettingsRepository` | `connection`, `key` |
| `file` | `FileSettingsRepository` | `path` |
| `array` | `ArraySettingsRepository` | — |

`SETTINGS_DRIVER` picks the default; every driver has its own `SETTINGS_*` variables (see the config file). [SettingsRepositoryFactory](src/BonsaiCms/Settings/SettingsRepositoryFactory.php) caches one instance per **name**, so the array driver keeps its contents for the life of the container, and `forgetDrivers()` is what re-reads config.

**Adding a driver:** implement `Contracts\SettingsRepository` with an `array $config` constructor, add a `driver_implementations` entry, add a `drivers` entry, and add it to the `repositories` dataset in `SettingsRepositoryTest` — the dataset is the only thing keeping the drivers from drifting apart.

Only the `database` driver needs the migration; `settings.migrations.driver` names the database driver whose `connection` and `table` the migration creates, so the two cannot fall out of step.

### The write-behind cache in SettingsManager

[SettingsManager](src/BonsaiCms/Settings/SettingsManager.php) holds an in-memory `Collection` cache plus two flags that drive nearly all of its logic:

- `$loadedAll` — set by `all()`. Once true, `getOne`/`getMany` **never** hit the repository again; a cache miss means the key genuinely does not exist. This is why the `LoadSettings` middleware makes subsequent `get()` calls query-free.
- `$dirty` — set by any `set()`. `SaveSettings` middleware only calls `save()` when dirty.

Values are held **deserialized** in the cache and serialized only in `save()`, which writes the *entire* cache back (single `setItem` when one entry, `setItems`/upsert otherwise). Nothing is persisted until `save()` runs.

`null` is the "absent" sentinel throughout: `has()` is `get() !== null`, serializers short-circuit on null, and *every* repository **drops** entries whose value is null rather than storing them — a deleted row, an `HDEL`, a key removed from the JSON. Keep that invariant when touching any of these; a driver that stores a null would make `has()` report a missing setting as present. It cuts both ways: `getOne`/`getMany` cache a null for a key that turned out not to exist (so reading it again is query-free), and `all()` therefore has to **filter those out** — the negative cache is an implementation detail, not a setting.

`getMany` keys its result off the *requested* keys, so the order matches the request whatever part of the answer came from the cache — the same guarantee every repository gives for `getItems()`.

Every repository method returns the raw serialized **string**, never a `Setting` model — the manager pipes whatever comes back straight into `deserialize()`.

### Serialization of objects

`serialize()` + `base64_encode()` into a `text` column. Objects implementing `Contracts\SerializationWrappable` are first wrapped in [SerializationWrapper](src/BonsaiCms/Settings/SerializationWrapper.php), which stores only the class name (`$c`) and a primitive payload (`$d`) — deliberately one-letter properties to keep the serialized string short. `unwrap()` calls the class's static `unwrapAfterSerialization()`. `Models\SerializableModelTrait` implements this for Eloquent by storing just the primary key and re-`find()`ing on read, so **model attributes are never serialized**.

Serializer/deserializer failures are swallowed and return `null` unless `settings.throwExceptions.*` is on (defaults to `env('APP_DEBUG')`). Both catch `Throwable`, not `Exception` — unwrapping a value whose class has since been renamed raises an `Error`, and one unreadable setting must not take the application down.

`unserialize()` reports a damaged string with a **warning**, not an exception, and answers `false` for a stored `false` and for garbage alike. `SettingsDeserializer::unserialize()` therefore installs its own error handler for the call and compares against `serialize(false)`: without that, the answer would depend on the host application's error handler, and a corrupted entry would come back as `false` — which is not null, so `has()` would call it present.

### Registration details

- `helpers/helpers.php` is in composer's `files` autoload, so `settings()` exists before any provider boots. It was `require_once`d from the provider's `boot()` until the Laravel 12/13 major, which made it order-dependent against other packages. Keep the `function_exists` guard: it is what stops a colliding `settings()` from another package from fataling.
- The `settings()` helper overloads on argument shape: an array with sequential integer keys is a multi-get, an associative array is a multi-set. See [helpers/helpers.php](helpers/helpers.php).
- `Models\Setting` is a plain model with a `bonsaicms_settings` fallback table. It does **not** read config: `DatabaseSettingsRepository` calls `setConnection()`/`setTable()` on a fresh instance per query, which is what lets one model class serve several database drivers.
- The migration is **not** registered with the application. It is published with `publishesMigrations()` under the `settings-migrations` tag (config under `settings-config`, both under `settings`), so the host app owns and can edit it. [tests/FeatureTestCase.php](tests/FeatureTestCase.php) therefore loads it itself in `defineDatabaseMigrations()` — feature tests get the table from there, not from the provider.

## Tests

Tests are written in **Pest**, in two suites bound to different base classes by [tests/Pest.php](tests/Pest.php):

- **`tests/Unit`** → `Tests\TestCase`, which extends Testbench but does **not** register `SettingsServiceProvider` and has no database. `ManagerTest` builds `SettingsManager` directly with Mockery mocks of the three collaborators (in `beforeEach`, exposed as `$this->manager` etc.) and asserts on the exact repository calls — so a change to *when* the manager calls the repository breaks it even if behaviour is externally identical. That is intentional; update the expectations deliberately.
- **`tests/Feature`** → `Tests\FeatureTestCase`, which registers the provider and the facade alias and runs against sqlite `:memory:` with `RefreshDatabase`. **Anything about repositories, the migration, or the manager's persistence belongs here** — the mocked unit tests cannot see a repository returning the wrong shape, which is precisely how `getItems()` shipped broken.

`SettingsRepositoryTest` runs the whole `SettingsRepository` contract against *every* implementation through the `repositories` dataset, so the drivers cannot drift apart. Dataset entries are *argument lists* — an array value needs one extra level of wrapping, and object/repository values are closures so every test gets a fresh instance. Redis and file keep their contents outside the PHP process, so unlike the database they are **not** rolled back between tests: the dataset closures empty them on the way in, and `FeatureTestCase::setUp()` empties the default driver.

A new expectation about a repository belongs in that dataset unless **only one driver can get it wrong**; each driver's own file — `DatabaseSettingsRepositoryTest`, `FileSettingsRepositoryTest`, `RedisSettingsRepositoryTest` — holds only that. They resolve their driver by name rather than through `settings.default`, so they run whatever `SETTINGS_DRIVER` the suite is pointed at.

The rest of `tests/Feature`:

| file | covers |
|---|---|
| `SettingsTest` | the package end to end through whatever driver is selected — **must stay driver agnostic** |
| `SettingsRepositoryFactoryTest` | driver resolution, and the `env()` wiring of the config file (by re-`require`ing it with the environment swapped) |
| `ServiceProviderTest` | the container bindings, the singletons, and swapping an implementation through the config |
| `MiddlewareTest` | `LoadSettings` / `SaveSettings` over real routes; "no more queries" is asserted by taking the repository away, not by counting SQL, so it holds for every driver |
| `DeleteAllSettingsCommandTest` | `settings:delete-all`, with and without `--driver` |
| `MigrationTest` | the published migration's `up()`/`down()` and its `settings.migrations.driver` wiring |
| `PublishingTest` | the publish tags, and that `vendor:publish` really writes the files |

In `tests/Unit`, `HelperTest` covers the `settings()` overload against a mocked manager, and `SerializerTest`/`DeserializerTest`/`SerializationWrappableTest` cover the value ⇄ string layer including its failure paths.

### Running the suite against a driver

Everything in `tests/Feature` is **driver agnostic** — keep it that way, and put assertions about rows, tables, hashes or files in the driver-specific tests. That is what lets the same suite run against each backend:

| variable | effect |
|---|---|
| `SETTINGS_DRIVER` | driver the whole suite runs against (`database`, `array`, `file`, `redis`) |
| `DB_DRIVER` + `DB_HOST`/`DB_PORT`/… | `sqlite` (default, `:memory:`), or a real `pgsql`/`mariadb`/`mysql` |
| `REDIS_HOST`/`REDIS_PORT`, `REDIS_CLIENT` | `predis` (default) or `phpredis` — they disagree on what `HMGET` returns and on how a missing field is reported, so both matter |
| `SETTINGS_REQUIRE_REDIS` | turns "no Redis, skip" into a failure |

Without a Redis running, the Redis tests **skip** so `composer test` still works on a bare machine. CI sets `SETTINGS_REQUIRE_REDIS=1` precisely so that skip cannot hide a broken driver. [.github/workflows/tests.yml](.github/workflows/tests.yml) has three jobs: `versions` (each PHP × Laravel on sqlite), `drivers` (whole suite once per `SETTINGS_DRIVER`, plus Redis on both clients) and `databases` (the database driver against real PostgreSQL, MariaDB and MySQL, because the upsert SQL is not the same on every engine and sqlite would never show it).

Most serializer tests assert round-trips through `SettingsDeserializer` rather than against fixed strings. Two do not, deliberately: `SerializerTest`'s "stores a value as base64 encoded php serialization" and `DeserializerTest`'s "reads back a value stored by an earlier version of the package" hold literal strings, because a round-trip keeps passing when the format changes on both sides at once — and changing it makes every setting already stored in the wild unreadable. Editing those literals is a deliberate act that needs a migration path, not a detail of some refactor.
