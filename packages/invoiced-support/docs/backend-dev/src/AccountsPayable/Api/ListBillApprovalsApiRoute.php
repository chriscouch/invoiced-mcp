<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\BillApproval;
use App\Core\RestApi\Libs\ApiCache;

/**
 * @extends AbstractListVendorDocumentApprovalsApiRoute<BillApproval>
 */
class ListBillApprovalsApiRoute extends AbstractListVendorDocumentApprovalsApiRoute
{
    public function __construct(ApiCache $apiCache)
    {
        parent::__construct(BillApproval::class, 'bill', $apiCache);
    }
}
