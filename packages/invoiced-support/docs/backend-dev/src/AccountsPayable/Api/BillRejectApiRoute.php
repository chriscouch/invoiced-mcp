<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Traits\BillResolveApiRouteTrait;

class BillRejectApiRoute extends VendorDocumentRejectApiRoute
{
    use BillResolveApiRouteTrait;
}
