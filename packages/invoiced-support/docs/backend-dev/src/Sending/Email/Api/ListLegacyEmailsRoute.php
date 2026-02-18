<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\Traits\UpdatedFilterTrait;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Sending\Email\Models\Email;

/**
 * @extends AbstractListModelsApiRoute<Email>
 */
class ListLegacyEmailsRoute extends AbstractListModelsApiRoute
{
    use UpdatedFilterTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Email::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        // updated timestamp filters
        $this->parseRequestUpdated($context->request);

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        return $this->addUpdatedFilterToQuery($query);
    }
}
