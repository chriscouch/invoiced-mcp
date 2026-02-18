<?php

namespace App\Metadata\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Metadata\Interfaces\MetadataModelInterface;
use App\Metadata\Libs\MetadataQuery;
use App\Metadata\ValueObjects\MetadataQueryCondition;
use Doctrine\DBAL\Connection;

abstract class ListModelsWithMetadataRoute extends AbstractListModelsApiRoute
{
    private array $metadata = [];

    public function __construct(
        protected TenantContext $tenant,
        protected Connection $database,
        ApiCache $apiCache,
    ) {
        parent::__construct($apiCache);
    }

    public function buildResponse(ApiCallContext $context): array
    {
        // parse metadata filter parameters
        $this->metadata = $context->request->query->all('metadata');

        return parent::buildResponse($context);
    }

    protected function getBaseQueryParameters(): array
    {
        $parameters = parent::getBaseQueryParameters();

        $parameters['metadata'] = new QueryParameter(
            types: ['array'],
            default: [],
        );

        return $parameters;
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        // sanitize and set the metadata filter
        $metadata = $this->parseMetadataInput($this->metadata);
        if ($this->model instanceof MetadataModelInterface) {
            $conditions = [];
            foreach ($metadata as $attributeName => $value) {
                $conditions[] = new MetadataQueryCondition($attributeName, (string) $value, '=');
            }

            MetadataQuery::addTo($query, $conditions);
        }

        return $query;
    }

    /**
     * Builds the metadata filter from an input array of parameters.
     *
     * @throws InvalidRequest when an invalid input parameter was used
     */
    protected function parseMetadataInput(array $input): array
    {
        if (0 === count($input)) {
            return [];
        }

        $filter = [];
        foreach ($input as $key => $value) {
            if (is_numeric($key) || !preg_match('/^[A-Za-z0-9_-]*$/', $key)) {
                throw new InvalidRequest("Invalid filter parameter: $key");
            }

            $filter[$key] = $value;
        }

        return $filter;
    }
}
