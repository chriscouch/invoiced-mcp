<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Exceptions\AutoPayException;
use App\PaymentProcessing\Operations\AutoPay;

class PayInvoiceRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private AutoPay $autoPay)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['charges.create'],
            modelClass: Invoice::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $invoice = parent::buildResponse($context);

        try {
            $this->autoPay->collect($invoice, AutoPay::PAYMENT_PLAN_MODE_NEXT);
        } catch (AutoPayException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $invoice;
    }
}
