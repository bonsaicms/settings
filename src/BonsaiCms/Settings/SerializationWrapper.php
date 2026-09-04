<?php

namespace BonsaiCms\Settings;

use BonsaiCms\Settings\Contracts\SerializationWrappable;
use BonsaiCms\Settings\Exceptions\DeserializeException;

/**
 * The envelope a SerializationWrappable object is stored as: its class name
 * and the primitive payload the class chose, and nothing else of the object
 * graph.
 */
class SerializationWrapper
{
    // We use short variable names to reduce serialized string length

    /**
     * Wrapped class name.
     *
     * Deliberately not narrowed to class-string<SerializationWrappable>: it
     * was one when it was written, and unwrap() exists precisely because by
     * the time it is read it may be anything at all - or nothing.
     *
     * @var class-string|string
     */
    protected string $c;

    /**
     * Wrapped data.
     */
    protected mixed $d;

    public function __construct(SerializationWrappable $wrappable)
    {
        $this->c = $wrappable::class;
        $this->d = $wrappable->wrapBeforeSerialization();
    }

    /**
     * Ask the class to rebuild itself from the payload.
     *
     * The guard lives here rather than in the classes themselves: an envelope
     * outlives the code that wrote it, so by the time it is read the class may
     * have been renamed, removed, or changed into something that no longer
     * knows how to unwrap.
     */
    public function unwrap(): mixed
    {
        if (! is_a($this->c, SerializationWrappable::class, true)) {
            throw new DeserializeException(
                "Cannot unwrap a stored [{$this->c}]: the class no longer exists, "
                .'or no longer implements SerializationWrappable.'
            );
        }

        return $this->c::unwrapAfterSerialization($this->d);
    }
}
