<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Exception\CustomerMergeException;
use App\AccountsReceivable\Libs\CustomerMerger;
use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

class MergeCustomerRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private CustomerMerger $merger)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['customers.edit'],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // this is second customer ID
        $customerId = (int) $context->request->request->get('customer');

        /** @var Customer $customer1 */
        $customer1 = parent::buildResponse($context);

        $customer2 = Customer::find($customerId);
        if (!$customer2) {
            throw new InvalidRequest('No such customer: '.$customerId);
        }

        if (!$customer1->active || !$customer2->active) {
            throw new InvalidRequest('Inactive customers cannot be merged');
        }

        try {
            $this->merger->merge($customer1, $customer2);
        } catch (CustomerMergeException $e) {
            throw new ApiError($e->getMessage());
        }

        return new Response('', 204);
    }
}
