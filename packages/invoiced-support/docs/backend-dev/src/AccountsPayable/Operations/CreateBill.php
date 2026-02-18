<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Traits\BillOperationTrait;

/**
 * @extends VendorDocumentCreateOperation<Bill>
 */
class CreateBill extends VendorDocumentCreateOperation
{
    use BillOperationTrait;
}
