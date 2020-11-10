<?php

namespace BonsaiCms\Settings\Contracts;

interface SerializationWrappable
{
    static function wrapBeforeSerialization($wrappable);

    static function unwrapAfterSerialization($wrappedClass, $wrappedValue);
}
