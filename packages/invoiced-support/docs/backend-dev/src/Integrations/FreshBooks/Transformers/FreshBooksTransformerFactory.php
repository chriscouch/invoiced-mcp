<?php

namespace App\Integrations\FreshBooks\Transformers;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\TransformerFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;

class FreshBooksTransformerFactory implements TransformerFactoryInterface
{
    public function __construct(
        private FreshBooksClientTransformer $customer,
        private FreshBooksInvoiceTransformer $invoice,
    ) {
    }

    public function get(ObjectType $type): TransformerInterface
    {
        return match ($type) {
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            default => throw new TransformException('Object type not supported'),
        };
    }
}
