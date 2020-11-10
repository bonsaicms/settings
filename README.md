# Introduction
There are some "settings packages" for Laravel out there. For example [anlutro/laravel-settings](https://github.com/anlutro/laravel-settings) or [akaunting/setting](https://github.com/akaunting/setting) but we think we can do better.

For example, this package is able to save **any value** in the settings (numbers, strings, booleans etc. but also any objects, for example [Eloquent](https://laravel.com/docs/8.x/eloquent) models).

# How it works

1. This package serialize the setting value (using the PHP's `serialize` function).
2. The value is stored it in a `binary` database column type (if you use `DatabaseSettingsRepository`).

# Installation
```bash2
$ composer require bonsaicms/settings
```

### Publish config file 
```bash2
$ php artisan vendor:publish --tag=settings
```

### Auto-save Middleware
Add the following line inside your `app/Http/Kernel.php` file. This middleware will automatically call `Settings::save()` after each request so you won't need to manually call it.
```php
    protected $middleware = [
        ...
+        \BonsaiCms\Settings\Http\Middleware\SaveSettings::class,
    ];
```

# Usage
```php
Settings::set('someting', 1);
Settings::get('someting'); // 1

Settings::set('someting', 1.2);
Settings::get('someting'); // 1.2

Settings::set('someting', true);
Settings::get('someting'); // true

Settings::set('someting', null);
Settings::get('someting'); // null

Settings::has('someting'); // true
Settings::has('sometingElse'); // false

// Eloquent models ...
$model = SomeEloquentModel::first();
Settings::set('model', $model);
Settings::get('model')->is($model); // true

// Mass...
Settings::set([
    'a' => 'A',
    'b' => 'B',
    'c' => 'C',
]);
Settings::get(['a', 'b', 'x']);
/* new Collection([
    'a' => 'A',
    'b' => 'B',
    'x' => null
]) */
```

## Set / Get Eloquent Models
TODO: you need to implement & include trait

This will **NOT serialize the model's attributes**! It will only serialize the model identity (model name + ID) and it will retrieve that model from the database when you call `Settings::get(...)`.
```php
// MyModel extends Eloquent
$model = MyModel::first(); // some model instance (not null)
Settings::set('model', $model);
$retrievedModel = Settings::get('model');
Settings::save();

// On the same or on the some future request as well...
$retrievedModel->is($model); // true
```
## Has
```php
Settings::set('someting', 1);

Settings::has('someting'); // true
Settings::has('sometingElse'); // false
```

## Save
```php
Settings::save(); // This will save changes into the repository (database)
```

## Delete
```php
Settings::set('someting', 1);
Settings::has('someting'); // true
Settings::deleteAll();
Settings::has('someting'); // false
Settings::get('someting'); // null
```

### Artisan command to delete settings
This will call `Settings::deleteAll()` under the hood.
```bash2
$ php artisan settings:delete-all
```

# Facade vs Helper
There is also a `settings()` helper available.
```php
Settings::set('a', 'b');
settings('a', 'b');

Settings::set(['a' => 'A', 'b' => 'B']);
settings(['a' => 'A', 'b' => 'B']);

Settings::get('a');
settings('a');

Settings::has('a');
settings()->has('a');

Settings::save();
settings()->save();

Settings::deleteAll();
settings()->deleteAll();
```

# Save your own objects in settings
Any of your classes can implement our `BonsaiCms\Settings\Contracts\SerializationWrappable` interface. It should then implement these two methods:

```php
interface SerializationWrappable
{
    static function wrapBeforeSerialization($wrappable);

    static function unwrapAfterSerialization($wrappedClass, $wrappedValue);
}
```

Example implementation:
```php
use BonsaiCms\Settings\Contracts\SerializationWrappable;

class MyClass implements SerializationWrappable
{
    /*
     * You should map the $wrappable object to some "wrapped value" here and return it.
     * The returned value should describe the object so you can re-create it again in the method below.
     * This value should be just primitive (string, number, array ...) because it will be serialized
     * and persisted in settings repository.
     */
    static function wrapBeforeSerialization($wrappable)
    {
        return [
            'something' => 'some-wrapped-value'        
        ];
    }

    /*
     * This method should return the original `$wrappable` passed to method above.
     */
    static function unwrapAfterSerialization($wrappedClass, $wrappedValue)
    {
        /*
         * $wrappedClass; // MyClass::class
         * $wrappedValue; // ['something' => 'some-wrapped-value']
         */
        return new MyClass; // You can pass $wrappedValue to the constructor
    }
}
```

Then you can simply write:

```php
$myObject = new MyClass;
Settings::set('obj', $myObject);
Settings::get('obj'); // same as $myObject (but probably not equal, depends on what you return in `unwrapAfterSerialization` method
Settings::save('obj');
```

# Tested with database systems
The values are stored it in a `binary` database column type (if you use `DatabaseSettingsRepository`).

- Postgres 10.14
- MariaDB 10.3
- MySQL 8.0
- MySQL 5.7

# Testing
```bash2
$ composer install
$ ./vendor/bin/phpunit 
```
