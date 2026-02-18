<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\PaymentProcessing\Models\CustomerPaymentBatchItem;

/**
 * @extends AbstractListModelsApiRoute<CustomerPaymentBatchItem>
 */
class ListCustomerPaymentBatchItemsRoute extends AbstractListModelsApiRoute
{
    private int $batchId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CustomerPaymentBatchItem::class,
            features: ['accounts_receivable'],
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
        $query->where('customer_payment_batch_id', $this->batchId);

        return $query;
    }
}
