<?php

namespace App\Integrations\BusinessCentral\Transformers;

use App\Core\Utils\Enums\ObjectType;
use App\Integrations\AccountingSync\Exceptions\TransformException;
use App\Integrations\AccountingSync\Interfaces\TransformerFactoryInterface;
use App\Integrations\AccountingSync\Interfaces\TransformerInterface;

class BusinessCentralTransformerFactory implements TransformerFactoryInterface
{
    public function __construct(
        private BusinessCentralCreditMemoTransformer $creditMemo,
        private BusinessCentralCustomerTransformer $customer,
        private BusinessCentralInvoiceTransformer $invoice,
    ) {
    }

    public function get(ObjectType $type): TransformerInterface
    {
        return match ($type) {
            ObjectType::CreditNote => $this->creditMemo,
            ObjectType::Customer => $this->customer,
            ObjectType::Invoice => $this->invoice,
            default => throw new TransformException('Object type not supported'),
        };
    }
}
