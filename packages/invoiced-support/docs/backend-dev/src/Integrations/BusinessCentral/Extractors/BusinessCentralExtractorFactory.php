<?php

namespace App\Integrations\BusinessCentral\Extractors;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\ExtractorFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;

class BusinessCentralExtractorFactory implements ExtractorFactoryInterface
{
    public function __construct(
        private BusinessCentralCreditMemoExtractor $creditMemo,
        private BusinessCentralCustomerExtractor $customer,
        private BusinessCentralInvoiceExtractor $invoice,
    ) {
    }

    public function get(ObjectType $type): ExtractorInterface
    {
        return match ($type) {
            ObjectType::CreditNote => $this->creditMemo,
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            default => throw new ExtractException('Object type not supported'),
        };
    }
}
