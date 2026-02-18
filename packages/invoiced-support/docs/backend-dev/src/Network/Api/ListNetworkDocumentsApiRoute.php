<?php

namespace App\Network\Api;

use App\Core\RestApi\Enum\FilterOperator;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\FilterCondition;
use App\Core\RestApi\ValueObjects\ListFilter;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Multitenant\TenantContext;
use App\Network\Models\NetworkConnection;
use App\Network\Models\NetworkDocument;
use App\Network\Traits\NetworkConnectionApiTrait;

/**
 * @extends AbstractListModelsApiRoute<NetworkDocument>
 */
class ListNetworkDocumentsApiRoute extends AbstractListModelsApiRoute
{
    use NetworkConnectionApiTrait;

    public function __construct(
        private TenantContext $tenant,
        ApiCache $apiCache
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'sent' => new QueryParameter(
                        required: true,
                        allowedValues: ['0', '1'],
                    ),
                    'from' => new QueryParameter(
                        default: null,
                    ),
                    'to' => new QueryParameter(
                        default: null,
                    ),
                ],
            ),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: NetworkDocument::class,
            filterableProperties: ['type', 'reference', 'current_status'],
            features: ['network'],
        );
    }

    public function parseFilterInput(ApiCallContext $context, array $input): ListFilter
    {
        $filter = parent::parseFilterInput($context, $input);

        if ($context->queryParameters['from']) {
            $networkConnection = NetworkConnection::find((int) $context->queryParameters['from']);
            if (!$networkConnection) {
                throw new InvalidRequest('Could not find network connection: '.$context->queryParameters['from']);
            }
            $filter = $filter->with(
                new FilterCondition(
                    operator: FilterOperator::Equal,
                    field: 'from_company_id',
                    value: $networkConnection->vendor_id,
                )
            );
        }

        if ($context->queryParameters['to']) {
            $networkConnection = NetworkConnection::find((int) $context->queryParameters['to']);
            if (!$networkConnection) {
                throw new InvalidRequest('Could not find network connection: '.$context->queryParameters['to']);
            }
            $filter = $filter->with(
                new FilterCondition(
                    operator: FilterOperator::Equal,
                    field: 'to_company_id',
                    value: $networkConnection->customer_id,
                )
            );
        }

        if ('1' == $context->queryParameters['sent']) {
            $filter = $filter->with(
                new FilterCondition(
                    operator: FilterOperator::Equal,
                    field: 'from_company_id',
                    value: $this->tenant->get(),
                )
            );
        } else {
            $filter = $filter->with(
                new FilterCondition(
                    operator: FilterOperator::Equal,
                    field: 'to_company_id',
                    value: $this->tenant->get(),
                )
            );
        }

        return $filter;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var NetworkDocument[] $documents */
        $documents = parent::buildResponse($context);

        $result = [];
        foreach ($documents as $document) {
            $item = $document->toArray();
            $item['from_company'] = $this->buildCompanyArray($document->from_company);
            $item['to_company'] = $this->buildCompanyArray($document->to_company);
            $result[] = $item;
        }

        return $result;
    }
}
