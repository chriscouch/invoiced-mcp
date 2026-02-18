<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Traits\VendorCreditResolveApiRouteTrait;

class VendorCreditApproveApiRoute extends VendorDocumentApproveApiRoute
{
    use VendorCreditResolveApiRouteTrait;
}
