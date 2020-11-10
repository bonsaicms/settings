<?php

namespace BonsaiCms\Settings\Contracts;

interface SettingsRepository
{
    function setItem(string $key, $value) : void;

    function setItems(array $items) : void;

    function getItem(string $key);

    function getItems(array $keys) : array;

    function getAll() : array;

    function deleteAll() : void;
}
