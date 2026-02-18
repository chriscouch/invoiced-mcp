<?php

namespace App\PaymentProcessing\ValueObjects;

use App\AccountsReceivable\Models\CreditNote;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Estimate;
use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;

final class PaymentAmountForm
{
    /**
     * @param PaymentAmountFormItem[] $lineItems
     */
    public function __construct(
        public readonly Company $company,
        public readonly Customer $customer,
        public readonly array $lineItems,
        public readonly string $currency,
    ) {
    }

    /**
     * Checks if there is more than 1 payment amount choice on any
     * line item OR if a user is required to enter an amount.
     */
    public function hasAvailableChoices(): bool
    {
        foreach ($this->lineItems as $lineItem) {
            if (count($lineItem->options) > 1) {
                return true;
            }

            foreach ($lineItem->options as $option) {
                if (!$option['amount']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getAvailableCreditNoteClientIds(): array
    {
        $result = [];
        foreach ($this->lineItems as $lineItem) {
            if ($lineItem->document instanceof CreditNote) {
                $result[] = $lineItem->document->client_id;
            }
        }

        return $result;
    }

    public function getAvailableEstimateClientIds(): array
    {
        $result = [];
        foreach ($this->lineItems as $lineItem) {
            if ($lineItem->document instanceof Estimate) {
                $result[] = $lineItem->document->client_id;
            }
        }

        return $result;
    }

    public function getAvailableInvoiceClientIds(): array
    {
        $result = [];
        foreach ($this->lineItems as $lineItem) {
            if ($lineItem->document instanceof Invoice) {
                $result[] = $lineItem->document->client_id;
            }
        }

        return $result;
    }
}
