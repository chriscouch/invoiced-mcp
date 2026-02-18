<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\BillRejection;
use App\Core\RestApi\Libs\ApiCache;

/**
 * @extends AbstractListVendorDocumentApprovalsApiRoute<BillRejection>
 */
class ListBillRejectionsApiRoute extends AbstractListVendorDocumentApprovalsApiRoute
{
    public function __construct(ApiCache $apiCache)
    {
        parent::__construct(BillRejection::class, 'bill', $apiCache);
    }
}
