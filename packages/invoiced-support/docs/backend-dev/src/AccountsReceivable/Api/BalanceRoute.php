<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Libs\CustomerBalanceGenerator;
use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class BalanceRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private CustomerBalanceGenerator $balanceGenerator)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $currency = $context->request->query->get('currency');

        /** @var Customer $customer */
        $customer = parent::buildResponse($context);

        return $this->balanceGenerator->generate($customer, $currency)
            ->toArray();
    }
}
