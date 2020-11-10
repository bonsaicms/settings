<?php

namespace Tests\Unit;

use stdClass;
use Tests\TestCase;
use BonsaiCms\Settings\SettingsSerializer;

class SerializerTest extends TestCase
{
    protected $settingsSerializer;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new SettingsSerializer;
    }

    public function testSerializeToNull()
    {
        $this->assertNull($this->serializer->serialize(null));
    }

    public function testSerializePrimitives()
    {
        $this->assertSerialized($this->serializer->serialize(''));
        $this->assertSerialized($this->serializer->serialize('test'));
        $this->assertSerialized($this->serializer->serialize(1));
        $this->assertSerialized($this->serializer->serialize(1.5));
        $this->assertSerialized($this->serializer->serialize(true));
        $this->assertSerialized($this->serializer->serialize(false));
        $this->assertSerialized($this->serializer->serialize([]));
        $this->assertSerialized($this->serializer->serialize([1,2,3]));
        $this->assertSerialized($this->serializer->serialize(['a' => 'A', 'b' => 'B']));
    }

    public function testSerializeObjects()
    {
        $this->assertSerialized($this->serializer->serialize(new stdClass));
        $this->assertSerialized($this->serializer->serialize((object)['a' => 'A', 'b' => 'B']));

        $object = new stdClass;
        $object->a = "A";
        $object->b = "B";
        $this->assertSerialized($this->serializer->serialize($object));
    }

    protected function assertSerialized($value)
    {
        $this->assertTrue(is_string($value));
        $this->assertTrue(strlen($value) > 0);
    }
}
