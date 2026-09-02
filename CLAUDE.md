# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`bonsaicms/settings` — a standalone Laravel package (not an application) providing a settings manager that can persist *any* PHP value, including Eloquent models and arbitrary objects. There is no `app/`, no `artisan`; the host application is simulated by `orchestra/testbench` in tests.

Supports PHP `^7.3|^8.0.2` and Laravel `^8.0|^9.0|^10.0|^11.0` — keep changes compatible across that whole range (no PHP 8-only syntax, no APIs newer than Laravel 8).

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

`phpunit.xml` targets the PHPUnit 9 schema, so newer PHPUnit/Pest prints one "deprecated schema" WARN above an otherwise green run. Leave it — migrating would break the PHPUnit 9 that Pest 1 (the PHP 7.3 / Laravel 8 end of the support matrix) pulls in.

Only `tests/Unit` is registered as a test suite in [phpunit.xml](phpunit.xml). There is no lint/static-analysis tooling in this repo.

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

`null` is the "absent" sentinel throughout: `has()` is `get() !== null`, serializers short-circuit on null, and `DatabaseSettingsRepository` **deletes** rows whose value is null rather than storing them. Keep that invariant when touching any of these.

### Serialization of objects

`serialize()` + `base64_encode()` into a `text` column. Objects implementing `Contracts\SerializationWrappable` are first wrapped in [SerializationWrapper](src/BonsaiCms/Settings/SerializationWrapper.php), which stores only the class name (`$c`) and a primitive payload (`$d`) — deliberately one-letter properties to keep the serialized string short. `unwrap()` calls the class's static `unwrapAfterSerialization()`. `Models\SerializableModelTrait` implements this for Eloquent by storing just the primary key and re-`find()`ing on read, so **model attributes are never serialized**.

Serializer/deserializer failures are swallowed and return `null` unless `settings.throwExceptions.*` is on (defaults to `env('APP_DEBUG')`).

### Registration details

- `helpers/helpers.php` is *not* in composer's `files` autoload — it is `require_once`d from the provider's `boot()`. The `settings()` helper is therefore unavailable unless the provider booted.
- The `settings()` helper overloads on argument shape: an array with sequential integer keys is a multi-get, an associative array is a multi-set. See [helpers/helpers.php](helpers/helpers.php).
- `Models\Setting` resolves its connection and table from config at call time (`getConnectionName()`/`getTable()`), so config changes take effect without touching the model.
- The migration is loaded automatically via `loadMigrationsFrom`; the config file is published under the `settings` tag.

## Tests

Tests are written in **Pest**. [tests/Pest.php](tests/Pest.php) binds `Tests\TestCase` to everything under `tests/Unit` via `uses(...)->in('Unit')` and declares the shared `primitives` / `objects` datasets — each dataset entry is an *argument list*, so an array value needs one extra level of wrapping, and object values are closures so every test gets a fresh instance.

`Tests\TestCase` extends Testbench but does **not** register `SettingsServiceProvider` and does not use a database. Manager tests construct `SettingsManager` directly with Mockery mocks of the three collaborators (built in `beforeEach`, exposed as `$this->manager` etc.) and assert on the exact repository calls — so a change to *when* the manager calls the repository will break `ManagerTest` even if behaviour is externally identical. That is intentional; update the expectations deliberately.

Serializer tests assert round-trips through `SettingsDeserializer`, not against fixed strings, so the on-disk format may change — but doing so breaks existing stored settings in the wild.
