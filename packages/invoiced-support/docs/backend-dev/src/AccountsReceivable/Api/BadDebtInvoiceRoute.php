<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Operations\SetBadDebt;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Exception\ModelException;

class BadDebtInvoiceRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private SetBadDebt $badDebt)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['invoices.edit'],
            modelClass: Invoice::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Invoice $invoice */
        $invoice = parent::buildResponse($context);

        try {
            $invoice = $this->badDebt->set($invoice);
        } catch (ModelException $e) {
            throw new ApiError($e->getMessage());
        }

        return $invoice;
    }
}
