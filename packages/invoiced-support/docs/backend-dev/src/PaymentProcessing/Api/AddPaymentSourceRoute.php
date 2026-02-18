<?php

namespace App\PaymentProcessing\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Operations\VaultPaymentInfo;

class AddPaymentSourceRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private VaultPaymentInfo $vaultPaymentInfo
    ) {
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
        $customer = parent::buildResponse($context);

        // determine the payment method (defaults to CCs)
        $company = $this->tenant->get();
        $type = array_value($context->requestParameters, 'method');
        if (!$type) {
            $type = PaymentMethod::CREDIT_CARD;
        }
        $method = PaymentMethod::instance($company, $type);

        $requestParameters = $context->requestParameters;

        $makeDefault = false;
        if (isset($requestParameters['make_default'])) {
            $makeDefault = $requestParameters['make_default'];
            unset($requestParameters['make_default']);
        }

        try {
            return $this->vaultPaymentInfo->save($method, $customer, $requestParameters, $makeDefault);
        } catch (PaymentSourceException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
