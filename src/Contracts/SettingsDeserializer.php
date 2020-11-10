<?php

namespace BonsaiCms\Settings\Contracts;

interface SettingsDeserializer
{
    function deserialize($serializedValue);
}
