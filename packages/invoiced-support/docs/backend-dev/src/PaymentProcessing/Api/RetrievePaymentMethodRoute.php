<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Libs\PaymentMethodDecorator;
use App\PaymentProcessing\Models\PaymentMethod;

class RetrievePaymentMethodRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private PaymentMethodDecorator $decorator
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PaymentMethod::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // add the company ID to the payment method ID
        $id = $context->request->attributes->get('model_id');
        $country = $context->request->query->get('country');
        $tenant = $this->tenant->get();
        $this->setModelIds([$tenant->id(), $id]);

        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = parent::buildResponse($context);

        return $this->decorator->decorate($paymentMethod, $country);
    }
}
