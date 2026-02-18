<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\CancelSubscription;

class CancelSubscriptionRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private CancelSubscription $cancelSubscription)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'canceled_reason' => new RequestParameter(
                    types: ['string', 'null'],
                    default: null,
                ),
            ],
            requiredPermissions: ['subscriptions.delete'],
            modelClass: Subscription::class,
            features: ['subscription_billing'],
        );
    }

    public function buildResponse(ApiCallContext $context): Subscription
    {
        /** @var Subscription $subscription */
        $subscription = parent::buildResponse($context);

        try {
            $this->cancelSubscription->cancel($subscription, $context->requestParameters['canceled_reason']);
        } catch (OperationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $subscription;
    }
}
