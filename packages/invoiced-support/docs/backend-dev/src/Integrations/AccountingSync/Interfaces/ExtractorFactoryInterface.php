<?php

namespace App\Integrations\AccountingSync\Interfaces;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\ExtractException;

interface ExtractorFactoryInterface
{
    /**
     * Gets an extractor for the given object type.
     *
     * @throws ExtractException
     */
    public function get(ObjectType $type): ExtractorInterface;
}
