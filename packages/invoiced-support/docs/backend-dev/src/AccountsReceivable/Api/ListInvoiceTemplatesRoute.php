<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Template;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractListModelsApiRoute<Template>
 */
class ListInvoiceTemplatesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Template::class,
            features: ['accounts_receivable'],
        );
    }
}
