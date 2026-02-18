<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\InvoiceDistribution;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

/**
 * Lists the distributions associated with an invoice.
 */
class ListInvoiceDistributions extends AbstractListModelsApiRoute
{
    private int $invoiceId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: InvoiceDistribution::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->invoiceId = (int) $context->request->attributes->get('model_id');

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        return $query->where('invoice_id', $this->invoiceId);
    }
}
