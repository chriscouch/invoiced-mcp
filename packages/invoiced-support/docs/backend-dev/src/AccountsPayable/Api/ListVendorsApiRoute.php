<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Ledger\VendorBalanceGenerator;
use App\AccountsPayable\Models\Vendor;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Database\Exception\QueryException;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;

/**
 * @extends AbstractListModelsApiRoute<Vendor>
 */
class ListVendorsApiRoute extends AbstractListModelsApiRoute
{
    public function __construct(
        protected TenantContext $tenant,
        private readonly VendorBalanceGenerator $balanceGenerator,
        protected readonly ListQueryBuilderFactory $listQueryBuilderFactory,
        ApiCache $apiCache,
    ) {
        parent::__construct($apiCache);
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                [
                    'automation' => new QueryParameter(
                        types: ['numeric', 'null'],
                        default: null,
                    ),
                ],
            ),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Vendor::class,
            features: ['accounts_payable'],
        );
    }

    protected function getOptions(ApiCallContext $context): array
    {
        return [
            'filter' => $this->filter,
            'advanced_filter' => $context->queryParameters['advanced_filter'] ?? null,
            'sort' => $context->queryParameters['sort'] ?? null,
            'automation' => $context->queryParameters['automation'] ?? null,
        ];
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var Vendor[] $vendors */
        $vendors = parent::buildResponse($context);

        if ($this->isParameterIncluded($context, 'balance')) {
            foreach ($vendors as $vendor) {
                $balance = $this->balanceGenerator->generate($vendor);
                $vendor->balance = [/* @phpstan-ignore-line */
                    'currency' => $balance->currency,
                    'amount' => $balance->toDecimal(),
                ];
            }
        }

        return $vendors;
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $builder = $this->listQueryBuilderFactory->get(
            Vendor::class,
            $this->tenant->get(),
            $this->getOptions($context),
        );

        try {
            return $builder->getBuildQuery($this->perPage);
        } catch (QueryException $e) {
            throw new InvalidRequest($e->getMessage(), 0, $e);
        }
    }
}
