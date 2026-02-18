<?php

namespace App\Integrations\SageAccounting\Transformers;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\TransformerFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;

class SageAccountingTransformerFactory implements TransformerFactoryInterface
{
    public function __construct(
        private SageAccountingCustomerTransformer $customer,
        private SageAccountingInvoiceTransformer $invoice,
        private SageAccountingCreditNoteTransformer $creditNote,
    ) {
    }

    public function get(ObjectType $type): TransformerInterface
    {
        return match ($type) {
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            ObjectType::CreditNote => $this->creditNote,
            default => throw new TransformException('Object type not supported'),
        };
    }
}
