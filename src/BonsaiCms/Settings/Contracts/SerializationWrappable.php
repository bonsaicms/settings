<?php

namespace BonsaiCms\Settings\Contracts;

/**
 * An object that decides for itself what of it is worth storing.
 *
 * Instead of serializing the object graph, the package stores the class name
 * plus whatever primitive payload the class hands over, and asks the class to
 * rebuild an instance from that payload on the way back.
 */
interface SerializationWrappable
{
    /**
     * The payload to store in place of this object. Keep it primitive and
     * small; it is what ends up in the settings store.
     */
    public function wrapBeforeSerialization(): mixed;

    /**
     * Rebuild an instance from a payload made by wrapBeforeSerialization().
     */
    public static function unwrapAfterSerialization(mixed $wrappedValue): mixed;
}
