<?php

namespace BonsaiCms\Settings;

use Throwable;
use BonsaiCms\Settings\Exceptions\SerializeException;
use BonsaiCms\Settings\Contracts\SerializationWrappable;
use BonsaiCms\Settings\Contracts\SettingsSerializer as SettingsSerializerContract;

class SettingsSerializer implements SettingsSerializerContract
{
    /**
     * Whether a value that cannot be serialized is worth an exception. The
     * flag is handed in rather than read from config() here, for the same
     * reason a repository takes its configuration: this class has no business
     * knowing where its settings come from, and it makes both answers
     * testable without touching the application config.
     */
    public function __construct(
        protected readonly bool $throwExceptions = false
    ) {
    }

    public function serialize(mixed $value): ?string
    {
        try {
            if ($value === null) {
                return null;
            }

            if ($value instanceof SerializationWrappable) {
                $value = new SerializationWrapper($value);
            }

            return base64_encode(serialize($value));
        } catch (Throwable $e) {
            // Throwable for the same reason as in SettingsDeserializer
            if ($this->throwExceptions) {
                throw ($e instanceof SerializeException)
                    ? $e
                    : new SerializeException($e->getMessage(), 0, $e);
            }

            return null;
        }
    }
}
