<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Traits\BillOperationTrait;

/**
 * @extends VendorDocumentVoidOperation<Bill>
 */
class VoidBill extends VendorDocumentVoidOperation
{
    use BillOperationTrait;
}
