<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\RemittanceAdvice;
use App\Core\RestApi\Traits\UpdatedFilterTrait;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;
use App\Metadata\Api\ListModelsWithMetadataRoute;

class ListRemittanceAdviceRoute extends ListModelsWithMetadataRoute
{
    use UpdatedFilterTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                $this->getUpdatedQueryParameters(),
            ),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: RemittanceAdvice::class,
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
