<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Traits\VendorCreditOperationTrait;

/**
 * @extends VendorDocumentVoidOperation<VendorCredit>
 */
class VoidVendorCredit extends VendorDocumentVoidOperation
{
    use VendorCreditOperationTrait;
}
