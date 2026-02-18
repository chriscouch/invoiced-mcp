<?php

namespace App\AccountsReceivable\Api;

use App\Chasing\Api\ListTasksRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Orm\Query;

class ListCustomerTasksRoute extends ListTasksRoute
{
    private int $customerId;

    public function buildResponse(ApiCallContext $context): array
    {
        $this->customerId = (int) $context->request->attributes->get('model_id');

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $query->with('user_id')
            ->where('customer_id', $this->customerId);

        return $query;
    }
}
