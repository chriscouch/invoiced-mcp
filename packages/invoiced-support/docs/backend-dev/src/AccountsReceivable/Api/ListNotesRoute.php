<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Note;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\Traits\UpdatedFilterTrait;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

class ListNotesRoute extends AbstractListModelsApiRoute
{
    use UpdatedFilterTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Note::class,
            filterableProperties: ['user_id', 'customer_id', 'invoice_id'],
            features: ['accounts_receivable'],
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
