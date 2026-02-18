<?php

namespace App\AccountsReceivable\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Core\Utils\Enums\ObjectType;
use App\PaymentProcessing\Models\DisabledPaymentMethod;

class ListCustomerDisabledPaymentMethodsRoute extends AbstractListModelsApiRoute
{
    private int $customerId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: DisabledPaymentMethod::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->customerId = (int) $context->request->attributes->get('customer_id');

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $query->where('object_type', ObjectType::Customer->typeName())
            ->where('object_id', $this->customerId);

        return $query;
    }
}
