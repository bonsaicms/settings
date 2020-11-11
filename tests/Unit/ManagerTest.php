<?php

namespace Tests\Unit;

use Mockery;
use Tests\TestCase;

use BonsaiCms\Settings\Contracts\SettingsRepository;
use BonsaiCms\Settings\Contracts\SettingsSerializer;
use BonsaiCms\Settings\Contracts\SettingsDeserializer;
use BonsaiCms\Settings\SettingsManager;

class ManagerTest extends TestCase
{
    protected $manager;
    protected $settingsRepository;
    protected $settingsSerializer;
    protected $settingsDeserializer;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = Mockery::mock(SettingsRepository::class);
        $this->settingsSerializer = Mockery::mock(SettingsSerializer::class);
        $this->settingsDeserializer = Mockery::mock(SettingsDeserializer::class);

        $this->manager = new SettingsManager(
            $this->settingsRepository,
            $this->settingsSerializer,
            $this->settingsDeserializer
        );
    }

    public function testGetRepository()
    {
        $this->assertEquals(
            $this->manager->getRepository(),
            $this->settingsRepository
        );

        $secondRepository = Mockery::mock(SettingsRepository::class);

        $this->assertNull(
            $this->manager->setRepository($secondRepository)
        );

        $this->assertEquals(
            $this->manager->getRepository(),
            $secondRepository
        );
    }

    public function testGetSerializer()
    {
        $this->assertEquals(
            $this->manager->getSerializer(),
            $this->settingsSerializer
        );

        $secondSerializer = Mockery::mock(SettingsSerializer::class);

        $this->assertNull(
            $this->manager->setSerializer($secondSerializer)
        );

        $this->assertEquals(
            $this->manager->getSerializer(),
            $secondSerializer
        );
    }

    public function testGetDeserializer()
    {
        $this->assertEquals(
            $this->manager->getDeserializer(),
            $this->settingsDeserializer
        );

        $secondDeserializer = Mockery::mock(SettingsDeserializer::class);

        $this->assertNull(
            $this->manager->setDeserializer($secondDeserializer)
        );

        $this->assertEquals(
            $this->manager->getDeserializer(),
            $secondDeserializer
        );
    }

    public function testShouldCallDeleteAllOnRepository()
    {
        $this->settingsRepository
            ->shouldReceive('deleteAll')
            ->once();
        $this->manager->deleteAll();

        config()->set('settings.autoload', true);
        $this->settingsRepository
            ->shouldReceive('deleteAll')
            ->once();
        $this->manager->deleteAll();
    }

    public function testGettersWithAutoload()
    {
        config()->set('settings.autoload', true);

        new SettingsManager(
            $this->settingsRepository,
            $this->settingsSerializer,
            $this->settingsDeserializer
        );

        $this->settingsRepository
            ->shouldReceive('getAll')
            ->once()
            ->andReturn([
                'a' => 'A-ser',
                'b' => 'B-ser',
            ]);
        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->once()
            ->with('A-ser')
            ->andReturn('A');
        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->once()
            ->with('B-ser')
            ->andReturn('B');

        $this->manager = new SettingsManager(
            $this->settingsRepository,
            $this->settingsSerializer,
            $this->settingsDeserializer
        );

        $this->assertEquals($this->manager->get('a'), 'A');
        $this->assertEquals($this->manager->get('b'), 'B');
        $this->assertEquals($this->manager->get('c'), null);

        $this->assertEquals(
            $this->toArray($this->manager->get(['a', 'b'])),
            [
                'a' => 'A',
                'b' => 'B',
            ]
        );

        $this->assertEquals(
            $this->toArray($this->manager->get(['a', 'b', 'c'])),
            [
                'a' => 'A',
                'b' => 'B',
                'c' => null,
            ]
        );

        $this->assertEquals(
            $this->toArray($this->manager->get(['a', 'c'])),
            [
                'a' => 'A',
                'c' => null,
            ]
        );

        $this->assertEquals(
            $this->toArray($this->manager->all()),
            [
                'a' => 'A',
                'b' => 'B',
                'c' => null,
            ]
        );

        $this->settingsRepository
            ->shouldReceive('deleteAll')
            ->once();
        $this->manager->deleteAll();

        $this->assertEquals(
            $this->toArray($this->manager->all()),
            []
        );

        $this->assertEquals($this->manager->get('a'), null);
        $this->assertEquals($this->manager->get('b'), null);
        $this->assertEquals($this->manager->get('c'), null);
    }

    public function testSettersWithAutoload()
    {
        config()->set('settings.autoload', true);

        $this->settingsRepository
            ->shouldReceive('getAll')
            ->once()
            ->andReturn([
                'a' => 'A-ser',
                'b' => 'B-ser',
            ]);
        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->once()
            ->with('A-ser')
            ->andReturn('A');
        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->once()
            ->with('B-ser')
            ->andReturn('B');

        $this->manager = new SettingsManager(
            $this->settingsRepository,
            $this->settingsSerializer,
            $this->settingsDeserializer
        );

        $this->manager->set('a', 'A2');

        $this->assertEquals($this->manager->get('a'), 'A2');
        $this->assertEquals($this->manager->get('b'), 'B');
        $this->assertEquals($this->manager->get('c'), null);

        $this->assertEquals(
            $this->toArray($this->manager->get(['a', 'b', 'c'])),
            [
                'a' => 'A2',
                'b' => 'B',
                'c' => null,
            ]
        );

        $this->manager->set([
            'b' => 'B2',
            'c' => 'C',
        ]);

        $this->assertEquals($this->manager->get('a'), 'A2');
        $this->assertEquals($this->manager->get('b'), 'B2');
        $this->assertEquals($this->manager->get('c'), 'C');
        $this->assertEquals($this->manager->get('d'), null);

        $this->assertEquals(
            $this->toArray($this->manager->get(['a', 'b', 'c', 'd'])),
            [
                'a' => 'A2',
                'b' => 'B2',
                'c' => 'C',
                'd' => null,
            ]
        );

        $this->assertEquals(
            $this->toArray($this->manager->all()),
            [
                'a' => 'A2',
                'b' => 'B2',
                'c' => 'C',
                'd' => null,
            ]
        );
    }

    public function testGettersAndSettersWithoutAutoload()
    {
        $this->manager->set('a', 'A');
        $this->assertEquals($this->manager->get('a'), 'A');

        $this->settingsRepository
            ->shouldReceive('getItems')
            ->once()
            ->with(['b']);

        $this->assertEquals(
            $this->toArray($this->manager->get(['a', 'b'])),
            [
                'a' => 'A',
                'b' => null,
            ]
        );

        $this->settingsRepository
            ->shouldReceive('getItems')
            ->once()
            ->with(['c', 'd', 'e']);

        $this->assertEquals(
            $this->toArray($this->manager->get(['c', 'd', 'e'])),
            [
                'c' => null,
                'd' => null,
                'e' => null,
            ]
        );

        $this->assertTrue($this->manager->has('a'));
        $this->assertFalse($this->manager->has('c'));

        $this->settingsRepository
            ->shouldReceive('getAll')
            ->once()
            ->andReturn([
                'd' => 'D-ser',
                'f' => 'F-ser',
            ]);
        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->once()
            ->with('F-ser')
            ->andReturn('F');

        $this->assertEquals(
            $this->toArray($this->manager->all()),
            [
                'a' => 'A',
                'b' => null,
                'c' => null,
                'd' => null,
                'e' => null,
                'f' => 'F',
            ]
        );
        $this->assertTrue($this->manager->has('f'));
    }

    public function testSaveOne()
    {
        $this->manager->save();

        $this->manager->set('a', 'A');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->once()
            ->with('A')
            ->andReturn('A-ser');

        $this->settingsRepository
            ->shouldReceive('setItem')
            ->once()
            ->with('a', 'A-ser');

        $this->manager->save();
    }

    public function testSaveMany()
    {
        $this->manager->set('a', 'A');
        $this->manager->set('b', 'B');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->once()
            ->with('A')
            ->andReturn('A-ser');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->once()
            ->with('B')
            ->andReturn('B-ser');

        $this->settingsRepository
            ->shouldReceive('setItems')
            ->once()
            ->with([
                'a' => 'A-ser',
                'b' => 'B-ser',
            ]);

        $this->manager->save();
    }

    public function testDeleteUsingSet()
    {
        $this->manager->set('a', 'A');
        $this->manager->set('b', 'B');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->once()
            ->with('A')
            ->andReturn('A-ser');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->once()
            ->with('B')
            ->andReturn('B-ser');

        $this->settingsRepository
            ->shouldReceive('setItems')
            ->once()
            ->with([
                'a' => 'A-ser',
                'b' => 'B-ser',
            ]);

        $this->manager->save();

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->once()
            ->with('A')
            ->andReturn('A-ser');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->once()
            ->with(null)
            ->andReturn(null);

        $this->settingsRepository
            ->shouldReceive('setItems')
            ->once()
            ->with([
                'a' => 'A-ser',
                'b' => null,
            ]);

        $this->manager->set('b', null);

        $this->manager->save();
    }
}
