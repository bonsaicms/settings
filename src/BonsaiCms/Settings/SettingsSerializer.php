<?php

namespace BonsaiCms\Settings;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\PostgresConnection;
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

            $dbConnection = DB::connection(Config::get('settings.database.connection'));
            if ($dbConnection instanceof PostgresConnection) {
                // Make sure we are connected to DB (https://stackoverflow.com/q/34235839/2987610)
                $dbConnection->getPdo();
                $value = pg_escape_bytea($value);
            }

            return $value;
        } catch (Exception $e) {
            if (Config::get('settings.throwExceptions.serialize')) {
                throw new SerializeException($e);
            }

            return null;
        }
    }
}
