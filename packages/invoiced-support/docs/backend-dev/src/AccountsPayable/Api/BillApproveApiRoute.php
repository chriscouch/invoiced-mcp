<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Traits\BillResolveApiRouteTrait;

class BillApproveApiRoute extends VendorDocumentApproveApiRoute
{
    use BillResolveApiRouteTrait;
}
