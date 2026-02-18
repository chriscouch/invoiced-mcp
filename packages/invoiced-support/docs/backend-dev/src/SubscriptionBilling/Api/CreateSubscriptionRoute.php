<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\CreateSubscription;

/**
 * API endpoint to create subscriptions.
 */
class CreateSubscriptionRoute extends AbstractCreateModelApiRoute
{
    public function __construct(private CreateSubscription $createSubscription)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['subscriptions.create'],
            modelClass: Subscription::class,
            features: ['subscription_billing'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        try {
            return $this->createSubscription->create($context->requestParameters);
        } catch (OperationException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
