<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Models\AchFileFormat;

/**
 * @extends AbstractListModelsApiRoute<AchFileFormat>
 */
class ListAchFileFormatsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: AchFileFormat::class,
            features: ['direct_ach'],
        );
    }
}
