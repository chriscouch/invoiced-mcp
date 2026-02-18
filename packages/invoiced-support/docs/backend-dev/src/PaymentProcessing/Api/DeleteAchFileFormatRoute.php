<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\AchFileFormat;

/**
 * @extends AbstractDeleteModelApiRoute<AchFileFormat>
 */
class DeleteAchFileFormatRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: AchFileFormat::class,
            features: ['direct_ach'],
        );
    }
}
