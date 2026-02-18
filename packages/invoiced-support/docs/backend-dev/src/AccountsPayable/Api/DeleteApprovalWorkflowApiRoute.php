<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\ApprovalWorkflow;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * @extends AbstractDeleteModelApiRoute<ApprovalWorkflow>
 */
class DeleteApprovalWorkflowApiRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: ApprovalWorkflow::class,
            features: ['accounts_payable'],
        );
    }

    public function retrieveModel(ApiCallContext $context)
    {
        $model = parent::retrieveModel($context);

        if ($model->getBillsCountValue() || $model->getVendorCreditsCountValue()) {
            throw new InvalidRequest("You can't delete workflow with assigned documents");
        }

        return $model;
    }
}
