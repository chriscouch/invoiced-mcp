<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorCreditApproval;
use App\Core\RestApi\Libs\ApiCache;

/**
 * @extends AbstractListVendorDocumentApprovalsApiRoute<VendorCreditApproval>
 */
class ListVendorCreditApprovalsApiRoute extends AbstractListVendorDocumentApprovalsApiRoute
{
    public function __construct(ApiCache $apiCache)
    {
        parent::__construct(VendorCreditApproval::class, 'vendor_credit', $apiCache);
    }
}
