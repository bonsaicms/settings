<?php

namespace BonsaiCms\Settings\Concerns;

/**
 * Makes an Eloquent model storable as a setting.
 *
 * Only the primary key is written to the store; the model is re-read on the
 * way out, so a setting holding a model always answers with the row as it is
 * now rather than with a snapshot of its attributes. A row that has since
 * been deleted therefore reads back as null - which is exactly what null
 * means everywhere else in this package.
 *
 * @see \BonsaiCms\Settings\SerializationWrapper for what happens when the
 *      model class itself is gone by the time the setting is read.
 */
trait SerializableModel
{
    public function wrapBeforeSerialization(): mixed
    {
        return $this->getKey();
    }

    public static function unwrapAfterSerialization(mixed $wrappedValue): mixed
    {
        return static::find($wrappedValue);
    }
}
