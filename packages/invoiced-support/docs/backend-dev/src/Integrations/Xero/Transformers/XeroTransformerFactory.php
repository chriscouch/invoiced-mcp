<?php

namespace App\Integrations\Xero\Transformers;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\TransformerFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;

class XeroTransformerFactory implements TransformerFactoryInterface
{
    public function __construct(
        private XeroCreditNoteTransformer $creditMemo,
        private XeroContactTransformer $customer,
        private XeroInvoiceTransformer $invoice,
        private XeroPaymentTransformer $payment,
    ) {
    }

    public function get(ObjectType $type): TransformerInterface
    {
        return match ($type) {
            ObjectType::CreditNote => $this->creditMemo,
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            ObjectType::Payment => $this->payment,
            default => throw new TransformException('Object type not supported'),
        };
    }
}
