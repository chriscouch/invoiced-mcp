<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Traits\VendorCreditOperationTrait;

/**
 * @extends VendorDocumentCreateOperation<VendorCredit>
 */
class CreateVendorCredit extends VendorDocumentCreateOperation
{
    use VendorCreditOperationTrait;
}
