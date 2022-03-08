<?php

namespace BonsaiCms\Settings;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\PostgresConnection;
use BonsaiCms\Settings\Exceptions\DeserializeException;
use BonsaiCms\Settings\Contracts\SettingsDeserializer as SettingsDeserializerContract;

class SettingsDeserializer implements SettingsDeserializerContract
{
    public function deserialize($value)
    {
        try {
            if ($value === null) {
                return null;
            }

            $value = base64_decode($value);

            $value = unserialize($value);

            if ($value === null) {
                return null;
            }

            if ($value instanceof SerializationWrapper) {
                $value = $value->unwrap();
            }

            return $value;
        } catch (Exception $e) {
            if (Config::get('settings.throwExceptions.deserialize')) {
                throw new DeserializeException($e);
            }

            return null;
        }
    }
}
