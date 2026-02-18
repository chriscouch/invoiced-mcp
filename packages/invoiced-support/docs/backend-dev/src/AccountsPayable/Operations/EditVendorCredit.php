<?php

namespace App\AccountsPayable\Operations;

use App\AccountsPayable\Models\VendorCredit;
use App\AccountsPayable\Traits\VendorCreditOperationTrait;

/**
 * @extends VendorDocumentEditOperation<VendorCredit>
 */
class EditVendorCredit extends VendorDocumentEditOperation
{
    use VendorCreditOperationTrait;
}
