<?php

namespace App\Integrations\SageAccounting\Extractors;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\ExtractorFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;

class SageAccountingExtractorFactory implements ExtractorFactoryInterface
{
    public function __construct(
        private SageAccountingCustomerExtractor $customer,
        private SageAccountingInvoiceExtractor $invoice,
        private SageAccountingCreditNoteExtractor $creditNote,
    ) {
    }

    public function get(ObjectType $type): ExtractorInterface
    {
        return match ($type) {
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            ObjectType::CreditNote => $this->creditNote,
            default => throw new ExtractException('Object type not supported'),
        };
    }
}
