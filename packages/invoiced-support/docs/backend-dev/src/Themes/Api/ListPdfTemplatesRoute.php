<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\PdfTemplate;

/**
 * @extends AbstractListModelsApiRoute<PdfTemplate>
 */
class ListPdfTemplatesRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: PdfTemplate::class,
            features: ['accounts_receivable'],
        );
    }
}
