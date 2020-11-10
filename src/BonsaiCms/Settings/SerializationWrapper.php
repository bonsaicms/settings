<?php

namespace BonsaiCms\Settings;

use BonsaiCms\Settings\Contracts\SerializationWrappable;

class SerializationWrapper
{
    // We use short variable names to reduce serialized string length

    // Wrapped class name
    protected $c;

    // Wrapped data
    protected $d;

    public function __construct(SerializationWrappable $wrappable)
    {
        $this->c = get_class($wrappable);
        $this->d = $wrappable::wrapBeforeSerialization($wrappable);
    }

    public function unwrap()
    {
        return $this->c::unwrapAfterSerialization($this->c, $this->d);
    }
}
