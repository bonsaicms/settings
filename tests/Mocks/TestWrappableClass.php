<?php

namespace Tests\Mocks;

use BonsaiCms\Settings\Contracts\SerializationWrappable;

final class TestWrappableClass implements SerializationWrappable
{
    public function __construct(
        protected mixed $secret
    ) {
    }

    public function getSecret(): mixed
    {
        return $this->secret;
    }

    public function wrapBeforeSerialization(): mixed
    {
        return ['secret' => $this->secret];
    }

    public static function unwrapAfterSerialization(mixed $wrappedValue): mixed
    {
        return new static($wrappedValue['secret'].'-was-unwrapped');
    }
}
