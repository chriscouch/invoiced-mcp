<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractRetrieveModelApiRoute<ApprovalWorkflow>
 */
class RetrieveApprovalWorkflowRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: ApprovalWorkflow::class,
            features: ['accounts_payable'],
        );
    }
}
