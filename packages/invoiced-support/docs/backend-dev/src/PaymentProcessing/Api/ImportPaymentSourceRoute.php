<?php

namespace App\PaymentProcessing\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Libs\ImportPaymentSource;

class ImportPaymentSourceRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private ImportPaymentSource $importPaymentSource
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['customers.edit'],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $customer = parent::buildResponse($context);

        $sourceValues = $context->requestParameters;

        try {
            $merchantAccount = $this->importPaymentSource->getMerchantAccountForGateway($sourceValues['gateway'] ?? '');

            return $this->importPaymentSource->import($customer, $sourceValues, $merchantAccount);
        } catch (PaymentSourceException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
