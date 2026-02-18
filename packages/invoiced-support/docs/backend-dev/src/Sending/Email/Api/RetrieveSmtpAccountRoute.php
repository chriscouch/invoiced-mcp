<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Models\SmtpAccount;

/**
 * @extends AbstractRetrieveModelApiRoute<SmtpAccount>
 */
class RetrieveSmtpAccountRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
            modelClass: SmtpAccount::class,
        );
    }
}
