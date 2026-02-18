<?php

namespace App\Reports\ValueObjects;

abstract class AbstractGroup
{
    abstract public function getType(): string;
}
