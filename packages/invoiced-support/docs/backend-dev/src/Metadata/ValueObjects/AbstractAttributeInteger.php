<?php

namespace App\Metadata\ValueObjects;

abstract class AbstractAttributeInteger extends Attribute
{
    protected function getPostfix(): string
    {
        return 'IntegerValues';
    }
}
