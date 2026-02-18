<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Sending\Email\Models\EmailThreadNote;

/**
 * @extends AbstractListModelsApiRoute<EmailThreadNote>
 */
class ListInboxThreadNotesRoute extends AbstractListModelsApiRoute
{
    private int $parentId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: EmailThreadNote::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->parentId = (int) $context->request->attributes->get('thread_id');

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $query->where('thread_id', $this->parentId);

        return $query;
    }
}
