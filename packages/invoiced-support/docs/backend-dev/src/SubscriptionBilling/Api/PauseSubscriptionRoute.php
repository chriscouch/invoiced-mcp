<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\PauseSubscription;

class PauseSubscriptionRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private PauseSubscription $pauseSubscription)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['subscriptions.edit'],
            modelClass: Subscription::class,
            features: ['subscription_billing'],
        );
    }

    public function buildResponse(ApiCallContext $context): Subscription
    {
        $subscription = parent::buildResponse($context);

        try {
            $this->pauseSubscription->pause($subscription);
        } catch (OperationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $subscription;
    }
}
