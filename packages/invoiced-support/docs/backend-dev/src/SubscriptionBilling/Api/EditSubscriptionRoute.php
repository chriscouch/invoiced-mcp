<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\EditSubscription;

class EditSubscriptionRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private EditSubscription $editSubscription)
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
        $subscription = parent::buildResponse($context);

        try {
            $this->editSubscription->modify($subscription, $context->requestParameters);
        } catch (OperationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $subscription;
    }
}
