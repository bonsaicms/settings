<?php

namespace BonsaiCms\Settings\Contracts;

use Illuminate\Support\Collection;

interface SettingsManager
{
    /**
     * @return SettingsRepository
     */
    function getRepository(): SettingsRepository;

    /**
     * @param SettingsRepository $repository
     */
    function setRepository(SettingsRepository $repository) : void;

    /**
     * @return SettingsSerializer
     */
    function getSerializer(): SettingsSerializer;

    /**
     * @param SettingsSerializer $serializer
     */
    function setSerializer(SettingsSerializer $serializer) : void;

    /**
     * @return SettingsDeserializer
     */
    function getDeserializer(): SettingsDeserializer;

    /**
     * @param SettingsDeserializer $deserializer
     */
    function setDeserializer(SettingsDeserializer $deserializer) : void;

    function set($keyOrPairs, $valueOrNull = null) : void;

    function get($keyOrKeys);

    function has($key) : bool;

    function all();

    function save() : void;

    function deleteAll() : void;
}
