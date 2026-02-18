<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\Bill;
use App\AccountsPayable\Traits\BillOperationTrait;

/**
 * @extends VendorDocumentEditOperation<Bill>
 */
class EditBill extends VendorDocumentEditOperation
{
    use BillOperationTrait;
}
