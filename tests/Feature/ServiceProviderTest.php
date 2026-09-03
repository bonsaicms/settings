<?php

use BonsaiCms\Settings\Contracts;
use BonsaiCms\Settings\SettingsFacade;
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
| Every seam of the package is an interface bound from the "implementations"
| config at register() time. What is asserted here is that each of them is
| bound, that the two stateful ones are singletons, and that swapping a class
| in the config really is all it takes to replace a piece.
|
*/

it('merges its config into the application', function () {
    expect(config('settings.drivers'))->toBeArray();
    expect(config('settings.driver_implementations'))->toBeArray();
    expect(config('settings.implementations'))->toBeArray();
    expect(config('settings.migrations.driver'))->toBe('database');
});

it('binds every contract in the implementations config', function (string $contract, string $implementation) {
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
     * The provider reads "settings.implementations" in register(), so this
     * registers it again with the config changed - which is what an
     * application publishing the config file effectively does at boot.
     */
    config()->set(
        'settings.implementations.'.Contracts\SettingsSerializer::class,
        SerializerOfMyOwn::class
    );

    (new SettingsServiceProvider(app()))->register();

    expect(app(Contracts\SettingsSerializer::class))->toBeInstanceOf(SerializerOfMyOwn::class);
});

it('lets the application swap the manager itself', function () {
    config()->set(
        'settings.implementations.'.Contracts\SettingsManager::class,
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
    public function serialize($deserializedValue)
    {
        return 'whatever';
    }
}

class ManagerOfMyOwn extends SettingsManager
{
    //
}
