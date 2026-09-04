<?php

use BonsaiCms\Settings\Contracts;
use BonsaiCms\Settings\Facades\Settings as SettingsFacade;
use BonsaiCms\Settings\SettingsManager;
use BonsaiCms\Settings\SettingsSerializer;
use BonsaiCms\Settings\SettingsDeserializer;
use BonsaiCms\Settings\SettingsRepositoryFactory;
use BonsaiCms\Settings\SettingsServiceProvider;

/*
|--------------------------------------------------------------------------
| The service provider wiring
|--------------------------------------------------------------------------
|
| Every seam of the package is an interface bound from the "bindings"
| config at register() time. What is asserted here is that each of them is
| bound, that the two stateful ones are singletons, and that swapping a class
| in the config really is all it takes to replace a piece.
|
*/

it('merges its config into the application', function () {
    expect(config('settings.drivers'))->toBeArray();
    expect(config('settings.driver_implementations'))->toBeArray();
    expect(config('settings.bindings'))->toBeArray();
    expect(config('settings.migrations.driver'))->toBe('database');
});

it('binds every contract in the bindings config', function (string $contract, string $implementation) {
    expect(app($contract))->toBeInstanceOf($implementation);
})->with([
    'manager' => [Contracts\SettingsManager::class, SettingsManager::class],
    'serializer' => [Contracts\SettingsSerializer::class, SettingsSerializer::class],
    'deserializer' => [Contracts\SettingsDeserializer::class, SettingsDeserializer::class],
    'factory' => [Contracts\SettingsRepositoryFactory::class, SettingsRepositoryFactory::class],
]);

/*
 * The repository contract is deliberately not in that config: it is bound to
 * whichever driver "settings.default" names, which SettingsRepositoryFactoryTest
 * covers along with the rest of the driver resolution.
 */

it('shares one manager with the whole application', function () {
    /*
     * The write behind cache only works because everything talks to the same
     * instance: the facade, the helper, the middleware and the command.
     */
    $manager = app(Contracts\SettingsManager::class);

    expect(app(Contracts\SettingsManager::class))->toBe($manager);
    expect(settings())->toBe($manager);
    expect(SettingsFacade::getFacadeRoot())->toBe($manager);
});

it('shares one repository factory, so a driver is built once', function () {
    $factory = app(Contracts\SettingsRepositoryFactory::class);

    expect(app(Contracts\SettingsRepositoryFactory::class))->toBe($factory);
});

it('points the facade at the manager contract', function () {
    expect(SettingsFacade::getFacadeRoot())->toBeInstanceOf(Contracts\SettingsManager::class);
});

it('registers the Settings alias', function () {
    expect(class_exists('Settings'))->toBeTrue();
});

it('lets the application swap an implementation from the config', function () {
    /*
     * "settings.bindings" is read when the binding is resolved rather than
     * when it is registered, so an application that publishes the config file
     * gets its own class without having to touch the container.
     */
    config()->set(
        'settings.bindings.'.Contracts\SettingsSerializer::class,
        SerializerOfMyOwn::class
    );

    expect(app(Contracts\SettingsSerializer::class))->toBeInstanceOf(SerializerOfMyOwn::class);
});

it('does not force a replacement to accept the arguments ours takes', function () {
    /*
     * The provider hands the serializer its throwExceptions flag by name, and
     * SerializerOfMyOwn has no such argument - a replacement is only ever
     * asked to honour the contract.
     */
    config()->set(
        'settings.bindings.'.Contracts\SettingsSerializer::class,
        SerializerOfMyOwn::class
    );
    config()->set('settings.throwExceptions.serialize', true);

    expect(app(Contracts\SettingsSerializer::class)->serialize('anything'))->toBe('whatever');
});

it('hands the serializers their failure reporting flag from the config', function (string $contract, string $option) {
    /*
     * The flag reaches them through the constructor rather than being read
     * from config() inside, so this wiring is the only place that knows the
     * two belong together.
     */
    config()->set("settings.throwExceptions.{$option}", true);

    $instance = app($contract);

    expect((new ReflectionProperty($instance, 'throwExceptions'))->getValue($instance))
        ->toBeTrue();
})->with([
    'serializer' => [Contracts\SettingsSerializer::class, 'serialize'],
    'deserializer' => [Contracts\SettingsDeserializer::class, 'deserialize'],
]);

it('lets the application swap the manager itself', function () {
    config()->set(
        'settings.bindings.'.Contracts\SettingsManager::class,
        ManagerOfMyOwn::class
    );

    app()->forgetInstance(Contracts\SettingsManager::class);
    (new SettingsServiceProvider(app()))->register();

    expect(app(Contracts\SettingsManager::class))->toBeInstanceOf(ManagerOfMyOwn::class);

    // And it is still the one thing the helper and the facade both reach
    expect(settings())->toBe(app(Contracts\SettingsManager::class));
});

class SerializerOfMyOwn implements Contracts\SettingsSerializer
{
    public function serialize(mixed $value): ?string
    {
        return 'whatever';
    }
}

class ManagerOfMyOwn extends SettingsManager
{
    //
}
