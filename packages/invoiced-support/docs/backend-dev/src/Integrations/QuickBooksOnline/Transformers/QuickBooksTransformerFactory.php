<?php

namespace App\Integrations\QuickBooksOnline\Transformers;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\TransformerFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;

class QuickBooksTransformerFactory implements TransformerFactoryInterface
{
    public function __construct(
        private QuickBooksCreditMemoTransformer $creditMemo,
        private QuickBooksCustomerTransformer $customer,
        private QuickBooksInvoiceTransformer $invoice,
        private QuickBooksItemTransformer $item,
        private QuickBooksPaymentTransformer $payment,
    ) {
    }

    public function get(ObjectType $type): TransformerInterface
    {
        return match ($type) {
            ObjectType::CreditNote => $this->creditMemo,
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            ObjectType::Item => $this->item,
            ObjectType::Payment => $this->payment,
            default => throw new TransformException('Object type not supported'),
        };
    }
}
