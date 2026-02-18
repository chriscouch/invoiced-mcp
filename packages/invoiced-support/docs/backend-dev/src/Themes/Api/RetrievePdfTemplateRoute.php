<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\PdfTemplate;

/**
 * @extends AbstractRetrieveModelApiRoute<PdfTemplate>
 */
class RetrievePdfTemplateRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: PdfTemplate::class,
            features: ['accounts_receivable'],
        );
    }
}
