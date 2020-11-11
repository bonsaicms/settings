<?php

namespace Tests;

use Illuminate\Support\Collection;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function toArray($mixed)
    {
        return ($mixed instanceof Collection)
            ? $mixed->toArray()
            : $mixed;
    }
}
