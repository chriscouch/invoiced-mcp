<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\Vendor;
use App\AccountsPayable\PaymentMethods\VendorPaymentMethods;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class VendorPaymentMethodsApiRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private VendorPaymentMethods $paymentMethods,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Vendor::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Vendor $vendor */
        $vendor = parent::buildResponse($context);

        return $this->paymentMethods->getForVendor($vendor);
    }
}
