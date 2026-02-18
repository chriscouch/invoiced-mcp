<?php

namespace App\Integrations\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\Textract\Models\TextractImport;

/**
 * @extends AbstractRetrieveModelApiRoute<TextractImport>
 */
class CheckExpenseDocumentImportStatusRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: ['bills.create'],
            modelClass: TextractImport::class,
        );
    }

    public function buildResponse(ApiCallContext $context): TextractImport
    {
        $import = parent::buildResponse($context);

        while (!$import->isCompleted()) {
            $importCandidate = $import::where('parent_job_id', $import->job_id)->oneOrNull();

            if (!$importCandidate) {
                break;
            }

            $import = $importCandidate;
        }

        return $import;
    }
}
