<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\TransformException;

interface TransformerFactoryInterface
{
    /**
     * Gets a transformer for the given object type.
     *
     * @throws TransformException
     */
    public function get(ObjectType $type): TransformerInterface;
}
