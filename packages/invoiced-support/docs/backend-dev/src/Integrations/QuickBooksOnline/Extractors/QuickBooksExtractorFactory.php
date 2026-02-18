<?php

namespace App\Integrations\QuickBooksOnline\Extractors;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\ExtractException;
use App\Integrations\AccountingSync\Interfaces\ExtractorFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\ExtractorInterface;

class QuickBooksExtractorFactory implements ExtractorFactoryInterface
{
    public function __construct(
        private QuickBooksCreditMemoExtractor $creditMemo,
        private QuickBooksCustomerExtractor $customer,
        private QuickBooksInvoiceExtractor $invoice,
        private QuickBooksItemExtractor $item,
        private QuickBooksPaymentExtractor $payment,
    ) {
    }

    public function get(ObjectType $type): ExtractorInterface
    {
        return match ($type) {
            ObjectType::CreditNote => $this->creditMemo,
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            ObjectType::Item => $this->item,
            ObjectType::Payment => $this->payment,
            default => throw new ExtractException('Object type not supported: '.$type->typeName()),
        };
    }
}
