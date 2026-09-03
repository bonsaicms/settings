<?php

namespace Tests\Mocks;

use Closure;

/**
 * An object PHP flatly refuses to serialize, because it holds a Closure.
 *
 * This is how the serializer's failure path gets exercised with a real
 * failure rather than a mocked one - the same thing that happens to an
 * application that stores a value holding a closure, a PDO handle or an open
 * file.
 */
class UnserializableClass
{
    public $callback;

    public function __construct()
    {
        $this->callback = Closure::fromCallable('strtolower');
    }
}
