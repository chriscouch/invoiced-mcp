<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\RenewManualContract;

class RenewManualContractRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private RenewManualContract $renewContract)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['subscriptions.edit'],
            modelClass: Subscription::class,
            features: ['subscription_billing'],
        );
    }

    public function buildResponse(ApiCallContext $context): Subscription
    {
        $cycles = (int) $context->request->request->get('cycles');

        /** @var Subscription $subscription */
        $subscription = parent::buildResponse($context);

        try {
            $this->renewContract->renew($subscription, $cycles);
        } catch (OperationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $subscription;
    }
}
