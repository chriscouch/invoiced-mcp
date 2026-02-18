<?php

namespace App\Core\RestApi\Routes;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Traits\UpdatedFilterTrait;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\Database\Exception\QueryException;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use Doctrine\DBAL\Connection;

abstract class AbstractListRoutesWithQueryBuilderRoute extends AbstractListModelsApiRoute
{
    use UpdatedFilterTrait;

    public function __construct(
        protected TenantContext $tenant,
        protected readonly ListQueryBuilderFactory $listQueryBuilderFactory,
        protected readonly Connection $database,
        ApiCache $apiCache,
    ) {
        parent::__construct($apiCache);
    }

    abstract protected function getOptions(ApiCallContext $context): array;

    public function buildResponse(ApiCallContext $context): array
    {
        // updated timestamp filters
        $this->parseRequestUpdated($context->request);

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $builder = $this->listQueryBuilderFactory->get(
            $this->model::class,
            $this->tenant->get(),
            $this->getOptions($context),
        );
        try {
            $query = $builder->getBuildQuery($this->perPage);
        } catch (QueryException $e) {
            throw new InvalidRequest($e->getMessage(), 0, $e);
        }

        return $this->addUpdatedFilterToQuery($query, $this->getModel()->getTablename());
    }
}
