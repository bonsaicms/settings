<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\Mocks\TestWrappableClass;
use BonsaiCms\Settings\SettingsSerializer;
use BonsaiCms\Settings\SettingsDeserializer;

class SerializationWrappableTest extends TestCase
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

    public function testWrapBeforeSerialization()
    {
        $secret = 'some-secret';

        $wrappableObject = new TestWrappableClass($secret);

        $serialized = $this->serializer->serialize($wrappableObject);

        $deserialized = $this->deserializer->deserialize($serialized);

        $this->assertEquals("{$secret}-was-unwrapped-".get_class($wrappableObject), $deserialized->getSecret());
    }
}
