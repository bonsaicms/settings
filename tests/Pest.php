<?php

use Tests\TestCase;
use Tests\FeatureTestCase;

uses(TestCase::class)->in('Unit');

// Feature tests boot the package inside a real app with a sqlite database
uses(FeatureTestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Datasets
|--------------------------------------------------------------------------
|
| Every entry is an argument list, so a value that is itself an array has to
| be wrapped one level deeper. Objects are built lazily inside a closure so
| each test gets a fresh instance.
|
*/

dataset('primitives', [
    'empty string' => [''],
    'string' => ['test'],
    'string with quotes' => ['text \' with " quotes'],
    'integer' => [1],
    'float' => [1.5],
    'true' => [true],
    'false' => [false],
    'empty array' => [[]],
    'list' => [[1, 2, 3]],
    'associative array' => [['a' => 'A', 'b' => 'B']],
]);

dataset('objects', [
    'empty object' => [fn () => new stdClass],
    'object cast from array' => [fn () => (object) ['a' => 'A', 'b' => 'B']],
    'object with assigned properties' => [function () {
        $object = new stdClass;
        $object->a = 'A';
        $object->b = 'B';

        return $object;
    }],
]);
