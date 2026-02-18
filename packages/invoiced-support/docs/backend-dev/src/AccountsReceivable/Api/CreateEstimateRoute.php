<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Estimate;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\DisabledPaymentMethod;

/**
 * API endpoint to create estimates.
 */
class CreateEstimateRoute extends AbstractCreateModelApiRoute
{
    private ?array $disabledPaymentMethods = null;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['estimates.create'],
            modelClass: Estimate::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;

        // disabled payment methods
        if (isset($requestParameters['disabled_payment_methods'])) {
            $this->disabledPaymentMethods = (array) $requestParameters['disabled_payment_methods'];
            unset($requestParameters['disabled_payment_methods']);
        }

        $context = $context->withRequestParameters($requestParameters);

        $estimate = parent::buildResponse($context);

        $this->createDisabledPaymentMethods($estimate);

        return $estimate;
    }

    private function createDisabledPaymentMethods(Estimate $estimate): void
    {
        if (!is_array($this->disabledPaymentMethods)) {
            return;
        }

        foreach ($this->disabledPaymentMethods as $k => $method) {
            $disabled = new DisabledPaymentMethod();
            $disabled->method = $method;
            $disabled->object_type = $estimate->object;
            $disabled->object_id = (string) $estimate->id();
            $disabled->saveOrFail();
        }
    }
}
