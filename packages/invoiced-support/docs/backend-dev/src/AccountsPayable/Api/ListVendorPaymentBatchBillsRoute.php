<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<VendorPaymentBatchBill>
 */
class ListVendorPaymentBatchBillsRoute extends AbstractListModelsApiRoute
{
    private int $batchId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorPaymentBatchBill::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->batchId = (int) $context->request->attributes->get('batch_id');

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);
        $query->where('vendor_payment_batch_id', $this->batchId);

        return $query;
    }
}
