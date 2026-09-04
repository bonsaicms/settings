<?php

namespace BonsaiCms\Settings\Exceptions;

class UnsupportedDriverException extends SettingsException
{
    public static function undefined(string $name): self
    {
        return new self(
            "Settings driver [{$name}] is not defined in the \"settings.drivers\" config."
        );
    }

    public static function missingType(string $name): self
    {
        return new self(
            "Settings driver [{$name}] does not declare a \"driver\" type."
        );
    }

    public static function unknownType(string $name, string $type): self
    {
        return new self(
            "Settings driver [{$name}] uses type [{$type}], which has no entry in "
            .'the "settings.driver_implementations" config.'
        );
    }
}
