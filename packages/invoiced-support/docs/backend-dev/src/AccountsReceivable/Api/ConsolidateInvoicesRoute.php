<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Exception\ConsolidationException;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Operations\InvoiceConsolidator;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class ConsolidateInvoicesRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(private InvoiceConsolidator $consolidator)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['invoices.create'],
            modelClass: Customer::class,
            features: ['consolidated_invoicing'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $cutoffDate = $context->request->request->getInt('cutoff_date') ?: null;

        $customer = parent::buildResponse($context);

        try {
            $invoice = $this->consolidator->consolidate($customer, $cutoffDate);
        } catch (ConsolidationException $e) {
            throw new ApiError($e->getMessage());
        }

        if (!$invoice) {
            throw new InvalidRequest('There were no invoices to consolidate for this customer');
        }

        return $invoice;
    }
}
