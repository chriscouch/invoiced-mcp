<?php

namespace App\Core\Files\Api;

use App\Core\Files\Models\Attachment;
use App\Core\Orm\Query;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * Lists the attachments associated with an object.
 */
class ListAttachmentsRoute extends AbstractListModelsApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Attachment::class,
        );
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $query->with('file_id')
            ->where('parent_type', $context->request->attributes->get('parent_type'))
            ->where('parent_id', (int) $context->request->attributes->get('parent_id'));

        return $query;
    }
}
