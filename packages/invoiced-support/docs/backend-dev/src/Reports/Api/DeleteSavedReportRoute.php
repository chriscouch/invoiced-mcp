<?php

namespace App\Reports\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Reports\Models\SavedReport;

/**
 * @extends AbstractDeleteModelApiRoute<SavedReport>
 */
class DeleteSavedReportRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['reports.create'],
            modelClass: SavedReport::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): SavedReport
    {
        $savedReport = parent::retrieveModel($context);

        $requester = ACLModelRequester::get();
        if (!$requester instanceof Member) {
            throw $this->permissionError();
        }

        if ($savedReport->creator_id != $requester->id && !$requester->allowed('business.admin')) {
            throw $this->permissionError();
        }

        return $savedReport;
    }
}
