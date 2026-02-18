<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractEditModelApiRoute<ApprovalWorkflow>
 */
class EnableWorkflowsApiRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: ApprovalWorkflow::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $context = $context->withRequestParameters(['enabled' => (bool) $context->request->attributes->get('enabled')]);

        return parent::buildResponse($context);
    }
}
