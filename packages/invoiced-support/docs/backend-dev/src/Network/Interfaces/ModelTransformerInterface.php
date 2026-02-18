<?php

namespace App\Network\Interfaces;

use App\Network\Exception\UblValidationException;

interface ModelTransformerInterface
{
    /**
     * Transforms an Invoiced model into a UBL document.
     *
     * @throws UblValidationException
     *
     * @return string UBL document as an XML string
     */
    public function transform(object $model, array $options): string;
}
