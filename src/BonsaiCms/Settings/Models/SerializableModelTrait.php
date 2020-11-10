<?php

namespace BonsaiCms\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use BonsaiCms\Settings\Exceptions\DeserializeException;

trait SerializableModelTrait
{
    static function wrapBeforeSerialization($model)
    {
        return $model->getKey();
    }

    static function unwrapAfterSerialization($modelClass, $modelId)
    {
        if (class_exists($modelClass) && is_subclass_of($modelClass, Model::class)) {
            return $modelClass::find($modelId);
        } else {
            throw new DeserializeException('Cannot deserialize Eloquent model');
        }
    }
}
