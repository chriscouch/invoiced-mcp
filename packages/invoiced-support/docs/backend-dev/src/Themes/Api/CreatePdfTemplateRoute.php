<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\PdfTemplate;

/**
 * @extends AbstractCreateModelApiRoute<PdfTemplate>
 */
class CreatePdfTemplateRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: PdfTemplate::class,
            features: ['accounts_receivable'],
        );
    }
}
