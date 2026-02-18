<?php

namespace App\Integrations\FreshBooks\Extractors;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\ExtractorFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;

class FreshBooksExtractorFactory implements ExtractorFactoryInterface
{
    public function __construct(
        private FreshBooksClientExtractor $customer,
        private FreshBooksInvoiceExtractor $invoice,
    ) {
    }

    public function get(ObjectType $type): ExtractorInterface
    {
        return match ($type) {
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            default => throw new ExtractException('Object type not supported'),
        };
    }
}
