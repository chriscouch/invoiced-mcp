<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Traits\VendorCreditResolveApiRouteTrait;

class VendorCreditRejectApiRoute extends VendorDocumentRejectApiRoute
{
    use VendorCreditResolveApiRouteTrait;
}
