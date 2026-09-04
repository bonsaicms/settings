<?php

namespace BonsaiCms\Settings;

use Throwable;
use BonsaiCms\Settings\Exceptions\DeserializeException;
use BonsaiCms\Settings\Contracts\SettingsDeserializer as SettingsDeserializerContract;

class SettingsDeserializer implements SettingsDeserializerContract
{
    /**
     * See SettingsSerializer for why this is handed in rather than read from
     * config() here.
     */
    public function __construct(
        protected readonly bool $throwExceptions = false
    ) {
    }

    public function deserialize(?string $value): mixed
    {
        try {
            if ($value === null) {
                return null;
            }

            $serialized = base64_decode($value, true);

            if ($serialized === false) {
                throw new DeserializeException('The stored value is not valid base64.');
            }

            $unserialized = $this->unserialize($serialized);

            /*
             * unserialize() answers false both for a stored false and for
             * anything it cannot read, telling them apart only by a warning.
             * Comparing against the one input that legitimately produces false
             * is what keeps a damaged entry from coming back as false - which
             * is not null, so has() would report a missing setting as present.
             */
            if ($unserialized === false && $serialized !== serialize(false)) {
                throw new DeserializeException('The stored value could not be unserialized.');
            }

            return ($unserialized instanceof SerializationWrapper)
                ? $unserialized->unwrap()
                : $unserialized;
        } catch (Throwable $e) {
            /*
             * Throwable and not Exception: unwrapping a value whose class has
             * since been renamed raises an Error, and taking the application
             * down over one unreadable setting is exactly what this swallowing
             * is here to avoid.
             */
            if ($this->throwExceptions) {
                throw ($e instanceof DeserializeException)
                    ? $e
                    : new DeserializeException($e->getMessage(), 0, $e);
            }

            return null;
        }
    }

    /**
     * unserialize() reports a damaged string with a warning rather than an
     * exception, and the "@" operator still hands that warning to whatever
     * error handler the host application installed - Laravel's turns it into
     * an ErrorException, another one might not. Handling the failure here
     * instead of leaving it to the host is what makes the answer the same
     * either way.
     */
    protected function unserialize(string $serialized): mixed
    {
        set_error_handler(static fn () => true);

        try {
            return unserialize($serialized);
        } finally {
            restore_error_handler();
        }
    }
}
