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

        $this->settingsRepository
            ->shouldReceive('deleteAll')
            ->once();
        $this->manager->deleteAll();
    }

    public function testGetItem()
    {
        $this->settingsRepository
            ->shouldReceive('getItem')
            ->with('a')
            ->once()
            ->andReturn('A-ser');

        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->once()
            ->with('A-ser')
            ->andReturn('A');

        $this->assertEquals($this->manager->get('a'), 'A');
    }

    public function testGetNullItem()
    {
        $this->settingsRepository
            ->shouldReceive('getItem')
            ->with('a')
            ->once()
            ->andReturn(null);

        $this->assertEquals($this->manager->get('a'), null);
    }

    public function testGetOneNonNullAndOneNullItem()
    {
        $this->settingsRepository
            ->shouldReceive('getItems')
            ->with(['a', 'b'])
            ->once()
            ->andReturn([
                'a' => 'A-ser',
                'b' => null,
            ]);

        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->once()
            ->with('A-ser')
            ->andReturn('A');

        $this->assertEquals($this->toArray($this->manager->get(['a', 'b'])), [
            'a' => 'A',
            'b' => null,
        ]);
    }

    public function testGetTwoNullItems()
    {
        $this->settingsRepository
            ->shouldReceive('getItems')
            ->with(['a', 'b'])
            ->once()
            ->andReturn([
                'a' => null,
                'b' => null,
            ]);

        $this->assertEquals($this->toArray($this->manager->get(['a', 'b'])), [
            'a' => null,
            'b' => null,
        ]);
    }

    public function testGetTwoNonNullItems()
    {
        $this->settingsRepository
            ->shouldReceive('getItems')
            ->with(['a', 'b'])
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

        $this->assertEquals($this->toArray($this->manager->get(['a', 'b'])), [
            'a' => 'A',
            'b' => 'B',
        ]);
    }

    public function testBasicSetItem()
    {
        $this->manager->set('a', 'A');
        $this->assertEquals($this->manager->get('a'), 'A');
    }

    public function testBasicOverrideItem()
    {
        $this->manager->set('a', 'A');
        $this->manager->set('a', 'A2');
        $this->assertEquals($this->manager->get('a'), 'A2');
    }

    public function testDeleteItem()
    {
        $this->manager->set('a', 'A');
        $this->manager->set('a', null);
        $this->assertEquals($this->manager->get('a'), null);
    }

    public function testBasicSetItemWithSave()
    {
        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->with('A')
            ->andReturn('A-ser');

        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', 'A-ser');

        $this->manager->set('a', 'A');
        $this->manager->save();
        $this->assertEquals($this->manager->get('a'), 'A');
    }

    public function testBasicSetItemWithSaveWithRefresh()
    {
        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->with('A')
            ->andReturn('A-ser');

        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', 'A-ser');

        $this->manager->set('a', 'A');
        $this->manager->save();

        $this->manager->refresh();

        $this->settingsRepository
            ->shouldReceive('getItem')
            ->with('a')
            ->andReturn('A-ser');
        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->with('A-ser')
            ->andReturn('A');
        $this->assertEquals($this->manager->get('a'), 'A');
    }

    public function testBasicOverrideItemWithSave()
    {
        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->with('A')
            ->andReturn('A-ser');
        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', 'A-ser');
        $this->manager->set('a', 'A');
        $this->manager->save();
        $this->assertEquals($this->manager->get('a'), 'A');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->with('A2')
            ->andReturn('A2-ser');
        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', 'A2-ser');
        $this->manager->set('a', 'A2');
        $this->manager->save();
        $this->assertEquals($this->manager->get('a'), 'A2');
    }

    public function testBasicOverrideItemWithSaveWithRefresh()
    {
        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->with('A')
            ->andReturn('A-ser');
        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', 'A-ser');
        $this->manager->set('a', 'A');
        $this->manager->save();
        $this->assertEquals($this->manager->get('a'), 'A');

        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->with('A2')
            ->andReturn('A2-ser');
        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', 'A2-ser');
        $this->manager->set('a', 'A2');
        $this->manager->save();

        $this->manager->refresh();

        $this->settingsRepository
            ->shouldReceive('getItem')
            ->with('a')
            ->andReturn('A2-ser');
        $this->settingsDeserializer
            ->shouldReceive('deserialize')
            ->with('A2-ser')
            ->andReturn('A2');
        $this->assertEquals($this->manager->get('a'), 'A2');
    }

    public function testDeleteItemWithSave()
    {
        $this->settingsSerializer
            ->shouldReceive('serialize')
            ->with('A')
            ->andReturn('A-ser');
        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', 'A-ser');
        $this->manager->set('a', 'A');
        $this->manager->save();
        $this->assertEquals($this->manager->get('a'), 'A');

        $this->settingsRepository
            ->shouldReceive('setItem')
            ->with('a', null);
        $this->manager->set('a', null);
        $this->manager->save();
        $this->assertEquals($this->manager->get('a'), null);
    }

    public function testSetTwoItems()
    {
        $this->manager->set([
            'a' => 'A',
            'b' => 'B',
        ]);

        $this->settingsSerializer->shouldReceive('serialize')->with('A')->andReturn('A-ser');
        $this->settingsSerializer->shouldReceive('serialize')->with('B')->andReturn('B-ser');

        $this->settingsRepository->shouldReceive('setItems')->with([
            'a' => 'A-ser',
            'b' => 'B-ser',
        ]);

        $this->manager->save();

        $this->assertEquals($this->manager->get('a'), 'A');
        $this->assertEquals($this->manager->get('b'), 'B');
        $this->assertEquals($this->toArray($this->manager->get(['a', 'b'])), [
            'a' => 'A',
            'b' => 'B',
        ]);

        $this->manager->refresh();

        $this->settingsRepository->shouldReceive('getItems')->with(['a', 'b'])->andReturn([
            'a' => 'A-ser',
            'b' => 'B-ser',
        ]);

        $this->settingsDeserializer->shouldReceive('deserialize')->with('A-ser')->andReturn('A');
        $this->settingsDeserializer->shouldReceive('deserialize')->with('B-ser')->andReturn('B');
        $this->assertEquals($this->toArray($this->manager->get(['a', 'b'])), [
            'a' => 'A',
            'b' => 'B',
        ]);
        $this->assertEquals($this->manager->get('a'), 'A');
        $this->assertEquals($this->manager->get('b'), 'B');
    }

    public function testIsDirtysOnSetValue()
    {
        $this->assertFalse($this->manager->isDirty());
        $this->manager->set('a', 'A');
        $this->assertTrue($this->manager->isDirty());
    }

    public function testIsDirtysOnSetNull()
    {
        $this->assertFalse($this->manager->isDirty());
        $this->manager->set('a', null);
        $this->assertTrue($this->manager->isDirty());
    }

    public function testIsDirtysOnSetMany()
    {
        $this->assertFalse($this->manager->isDirty());
        $this->manager->set([
            'a' => 'A',
            'b' => null
        ]);
        $this->assertTrue($this->manager->isDirty());
    }

    public function testIsDirtysOnSetManyEmpty()
    {
        $this->assertFalse($this->manager->isDirty());
        $this->manager->set([]);
        $this->assertFalse($this->manager->isDirty());
    }

    public function testIsDirtysIsFalseAfterRefresh()
    {
        $this->assertFalse($this->manager->isDirty());
        $this->manager->set('a', 'A');
        $this->assertTrue($this->manager->isDirty());
        $this->manager->refresh();
        $this->assertFalse($this->manager->isDirty());
    }

    public function testIsDirtysIsFalseAfterSave()
    {
        $this->settingsSerializer->shouldReceive('serialize')->with('A')->andReturn('A-ser');
        $this->settingsRepository->shouldReceive('setItem')->with('a', 'A-ser');

        $this->assertFalse($this->manager->isDirty());
        $this->manager->set('a', 'A');
        $this->assertTrue($this->manager->isDirty());
        $this->manager->save();
        $this->assertFalse($this->manager->isDirty());
    }
}
