<?php

namespace App\Core;

use Aws\Result;

class NullFileProxy
{
    public function __call(string $name, array $arguments): mixed
    {
        return new Result([]);
    }
}
