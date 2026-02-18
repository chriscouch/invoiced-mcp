<?php

namespace App\Integrations\Intacct\Api;

use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Intacct\Models\IntacctSyncProfile;

/**
 * @extends AbstractCreateModelApiRoute<IntacctSyncProfile>
 */
class CreateIntacctSyncProfileRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: IntacctSyncProfile::class,
            features: ['intacct'],
        );
    }
}
