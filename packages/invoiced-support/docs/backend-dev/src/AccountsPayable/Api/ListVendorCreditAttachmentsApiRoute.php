<?php

namespace App\AccountsPayable\Api;

use App\Core\Files\Models\VendorCreditAttachment;
use App\Core\Orm\Query;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * Lists the attachments associated with the vendor payment.
 */
class ListVendorCreditAttachmentsApiRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorCreditAttachment::class,
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $query->with('file')
            ->where('vendor_credit_id', (int) $context->request->attributes->get('parent_id'));

        return $query;
    }
}
