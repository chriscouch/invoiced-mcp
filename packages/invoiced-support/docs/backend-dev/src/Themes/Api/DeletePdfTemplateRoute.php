<?php

namespace App\Themes\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Themes\Models\PdfTemplate;

/**
 * @extends AbstractDeleteModelApiRoute<PdfTemplate>
 */
class DeletePdfTemplateRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: PdfTemplate::class,
            features: ['accounts_receivable'],
        );
    }
}
