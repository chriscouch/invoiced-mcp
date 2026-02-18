<?php

namespace App\SubscriptionBilling\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\SubscriptionBilling\Exception\OperationException;
use App\SubscriptionBilling\Models\Subscription;
use App\SubscriptionBilling\Operations\ResumeSubscription;
use Carbon\CarbonImmutable;

class ResumeSubscriptionRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private ResumeSubscription $resumeSubscription)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'period_end' => new RequestParameter(
                    types: ['integer', 'null'],
                    default: null,
                ),
            ],
            requiredPermissions: ['subscriptions.edit'],
            modelClass: Subscription::class,
            features: ['subscription_billing'],
        );
    }

    public function buildResponse(ApiCallContext $context): Subscription
    {
        $subscription = parent::buildResponse($context);
        $periodEnd = $context->requestParameters['period_end'] ? CarbonImmutable::createFromTimestamp($context->requestParameters['period_end']) : null;

        try {
            $this->resumeSubscription->resume($subscription, $periodEnd);
        } catch (OperationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $subscription;
    }
}
