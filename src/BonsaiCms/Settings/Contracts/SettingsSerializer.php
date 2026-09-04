<?php

namespace BonsaiCms\Settings\Contracts;

interface SettingsSerializer
{
    /**
     * Turn a value into the string a repository stores, or null for a value
     * that cannot be stored - null included, which is how a delete travels.
     */
    public function serialize(mixed $value): ?string;
}
