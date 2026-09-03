<?php

namespace BonsaiCms\Settings;

use Throwable;
use Illuminate\Support\Facades\Config;
use BonsaiCms\Settings\Exceptions\SerializeException;
use BonsaiCms\Settings\Contracts\SerializationWrappable;
use BonsaiCms\Settings\Contracts\SettingsSerializer as SettingsSerializerContract;

class SettingsSerializer implements SettingsSerializerContract
{
    public function serialize($value)
    {
        try {
            if ($value === null) {
                return null;
            }

            if ($value instanceof SerializationWrappable) {
                $value = new SerializationWrapper($value);
            }

            $value = serialize($value);

            $value = base64_encode($value);

            return $value;
        } catch (Throwable $e) {
            // Throwable for the same reason as in SettingsDeserializer
            if (Config::get('settings.throwExceptions.serialize')) {
                throw ($e instanceof SerializeException)
                    ? $e
                    : new SerializeException($e->getMessage(), 0, $e);
            }

            return null;
        }
    }
}
