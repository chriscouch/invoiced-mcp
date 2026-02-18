<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\InvoiceDistribution;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<InvoiceDistribution>
 */
class EditInvoiceDistributionRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'email' => new RequestParameter(),
                'template' => new RequestParameter(),
                'department' => new RequestParameter(),
                'enabled' => new RequestParameter(),
            ],
            requiredPermissions: ['invoices.edit'],
            modelClass: InvoiceDistribution::class,
            features: ['accounts_receivable'],
        );
    }
}
