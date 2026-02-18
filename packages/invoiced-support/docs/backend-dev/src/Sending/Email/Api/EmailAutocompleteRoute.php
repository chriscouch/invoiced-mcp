<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Multitenant\TenantContext;
use App\Core\Search\Driver\Elasticsearch\ElasticsearchDriver;
use App\Core\Search\Exceptions\SearchException;
use App\Sending\Email\Models\EmailParticipant;

class EmailAutocompleteRoute extends AbstractModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private readonly ElasticsearchDriver $elasticsearch
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [
                'term' => new QueryParameter(),
                'limit' => new QueryParameter(
                    default: 10,
                ),
                // select2 passes this option to prevent caching
                '_' => new QueryParameter(
                    default: null,
                ),
            ],
            requestParameters: null,
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $term = $context->queryParameters['term'];
        if (strlen($term) < 3) {
            throw new InvalidRequest('Search term must be at least 3 characters long');
        }

        try {
            $result = $this->elasticsearch->search($this->tenant->get(), $term, EmailParticipant::class, $context->queryParameters['limit']);
        } catch (SearchException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return array_map(function ($item) {
            if (is_array($item['email_address'])) {
                $item['email_address'] = $item['email_address'][0];
            }

            return $item;
        }, $result);
    }
}
