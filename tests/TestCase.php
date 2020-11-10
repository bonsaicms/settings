<?php

namespace Tests;

use Illuminate\Support\Collection;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('settings', [
            'autoload' => false,
        ]);
    }

    protected function toArray($mixed)
    {
        return ($mixed instanceof Collection)
            ? $mixed->toArray()
            : $mixed;
    }
}
