<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Contact;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

/**
 * Lists the contacts associated with a customer.
 */
class ListContactsRoute extends AbstractListModelsApiRoute
{
    private int $customerId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Contact::class,
            filterableProperties: ['customer_id'],
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

        $query->where('customer_id', $this->customerId);

        return $query;
    }
}
