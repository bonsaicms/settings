<?php

namespace Tests\Unit;

use stdClass;
use Tests\TestCase;
use BonsaiCms\Settings\SettingsSerializer;
use BonsaiCms\Settings\SettingsDeserializer;

class DeserializerTest extends TestCase
{
    protected $settingsSerializer;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new SettingsSerializer;
        $this->deserializer = new SettingsDeserializer;
    }

    public function testDeserializeToNull()
    {
        $this->assertNull($this->deserializer->deserialize(null));
    }

    public function testDeserializePrimitives()
    {
        $this->assertProcess('');
        $this->assertProcess('test');
        $this->assertProcess(1);
        $this->assertProcess(1.5);
        $this->assertProcess(true);
        $this->assertProcess(false);
        $this->assertProcess([]);
        $this->assertProcess([1,2,3]);
        $this->assertProcess(['a' => 'A', 'b' => 'B']);
    }

    public function testDeserializeObjects()
    {
        $this->assertProcess(new stdClass);
        $this->assertProcess((object)['a' => 'A', 'b' => 'B']);

        $object = new stdClass;
        $object->a = "A";
        $object->b = "B";
        $this->assertProcess($object);
    }

    protected function assertProcess($original)
    {
        $serialized = $this->serializer->serialize($original);
        $deserialized = $this->deserializer->deserialize($serialized);
        $this->assertEquals($original, $deserialized);
    }
}
