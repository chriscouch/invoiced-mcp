<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorCreditRejection;
use App\Core\RestApi\Libs\ApiCache;

/**
 * @extends AbstractListVendorDocumentApprovalsApiRoute<VendorCreditRejection>
 */
class ListVendorCreditRejectionsApiRoute extends AbstractListVendorDocumentApprovalsApiRoute
{
    public function __construct(ApiCache $apiCache)
    {
        parent::__construct(VendorCreditRejection::class, 'vendor_credit', $apiCache);
    }
}
