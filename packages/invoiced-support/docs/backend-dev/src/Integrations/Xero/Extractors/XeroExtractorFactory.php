<?php

namespace App\Integrations\Xero\Extractors;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\ExtractorFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;

class XeroExtractorFactory implements ExtractorFactoryInterface
{
    public function __construct(
        private XeroCreditNoteExtractor $creditMemo,
        private XeroContactExtractor $customer,
        private XeroInvoiceExtractor $invoice,
        private XeroPaymentExtractor $payment,
    ) {
    }

    public function get(ObjectType $type): ExtractorInterface
    {
        return match ($type) {
            ObjectType::CreditNote => $this->creditMemo,
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            ObjectType::Payment => $this->payment,
            default => throw new ExtractException('Object type not supported'),
        };
    }
}
