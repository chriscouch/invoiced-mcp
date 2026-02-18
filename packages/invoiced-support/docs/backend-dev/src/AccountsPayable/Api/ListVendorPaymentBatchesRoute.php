<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPaymentBatch;
use App\AccountsPayable\Models\VendorPaymentBatchBill;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<VendorPaymentBatch>
 */
class ListVendorPaymentBatchesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        $params = $this->getBaseQueryParameters();
        $params['bill_id'] = new QueryParameter(
            types: ['numeric', 'null'],
            default: null,
        );

        return new ApiRouteDefinition(
            queryParameters: $params,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: VendorPaymentBatch::class,
            features: ['accounts_payable'],
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        if ($context->queryParameters['bill_id']) {
            $query->join(VendorPaymentBatchBill::class, 'VendorPaymentBatches.id', 'VendorPaymentBatchBills.vendor_payment_batch_id')
                ->where('VendorPaymentBatchBills.bill_id', $context->queryParameters['bill_id']);
        }

        return $query;
    }
}
