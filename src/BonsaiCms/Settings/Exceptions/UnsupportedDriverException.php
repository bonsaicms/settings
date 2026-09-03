<?php

namespace BonsaiCms\Settings\Exceptions;

class UnsupportedDriverException extends AbstractException
{
    public static function undefined(string $name) : static
    {
        return new static(
            "Settings driver [{$name}] is not defined in the \"settings.drivers\" config."
        );
    }

    public static function missingType(string $name) : static
    {
        return new static(
            "Settings driver [{$name}] does not declare a \"driver\" type."
        );
    }

    public static function unknownType(string $name, string $type) : static
    {
        return new static(
            "Settings driver [{$name}] uses type [{$type}], which has no entry in "
            .'the "settings.driver_implementations" config.'
        );
    }
}
