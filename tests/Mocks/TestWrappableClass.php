<?php

namespace Tests\Mocks;

use BonsaiCms\Settings\Contracts\SerializationWrappable;

class TestWrappableClass implements SerializationWrappable
{
    protected $secret;

    public function __construct($secret)
    {
        $this->secret = $secret;
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public static function wrapBeforeSerialization($object)
    {
        return [
            'secret' => $object->getSecret(),
        ];
    }

    public static function unwrapAfterSerialization($wrappedClass, $wrappedValue)
    {
        return new static($wrappedValue['secret'] . '-was-unwrapped-' . $wrappedClass);
    }
}
