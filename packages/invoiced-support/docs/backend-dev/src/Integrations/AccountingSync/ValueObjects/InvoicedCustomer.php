<?php

namespace App\Integrations\AccountingSync\ValueObjects;

use App\AccountsReceivable\Models\Customer;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;

final class InvoicedCustomer
{
    public function __construct(
        public readonly Customer $customer,
        public readonly ?AccountingCustomerMapping $mapping = null,
    ) {
    }
}
