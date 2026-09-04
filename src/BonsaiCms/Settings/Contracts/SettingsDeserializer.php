<?php

namespace BonsaiCms\Settings\Contracts;

interface SettingsDeserializer
{
    /**
     * Turn a stored string back into the value it was made from, or null when
     * there is nothing to read or the stored string cannot be read.
     */
    public function deserialize(?string $value): mixed;
}
