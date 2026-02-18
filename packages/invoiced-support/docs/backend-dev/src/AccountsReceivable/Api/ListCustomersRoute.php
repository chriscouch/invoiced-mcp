<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Libs\ApiCache;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\Traits\UpdatedFilterTrait;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Database\Exception\QueryException;
use App\Core\ListQueryBuilders\ListQueryBuilderFactory;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\Query;
use App\Reports\Libs\AgingReport;
use App\Reports\ValueObjects\AgingBreakdown;
use Doctrine\DBAL\Connection;

class ListCustomersRoute extends AbstractListModelsApiRoute
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

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                $this->getUpdatedQueryParameters(),
                [
                    'owner' => new QueryParameter(
                        default: null,
                    ),
                    'open_balance' => new QueryParameter(
                        default: null,
                    ),
                    'balance' => new QueryParameter(
                        default: null,
                    ),
                    'payment_source' => new QueryParameter(
                        default: null,
                    ),
                    'late_fee_schedule_id' => new QueryParameter(
                        default: null,
                    ),
                    'automation' => new QueryParameter(
                        types: ['numeric', 'null'],
                        default: null,
                    ),
                    'metadata' => new QueryParameter(
                        default: null,
                    ),
                ],
            ),
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Customer::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->parseRequestUpdated($context->request);

        $customers = parent::buildResponse($context);

        // add in the aging for the customers if requested
        if ($this->isParameterIncluded($context, 'aging')) {
            return $this->withAging($customers);
        }

        return $customers;
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        // rewrite `chasing_cadence` filter to `chasing_cadence_id`
        if (isset($this->filter['chasing_cadence'])) {
            $this->filter['chasing_cadence_id'] = $this->filter['chasing_cadence'];
            unset($this->filter['chasing_cadence']);
        }

        $options = [
            'filter' => $this->filter,
            'sort' => $this->getSort(),
            'owner' => (int) $context->queryParameters['owner'],
            'automation' => $context->queryParameters['automation'] ?? null,
            'advanced_filter' => $context->queryParameters['advanced_filter'] ?? null,
            'metadata' => $context->queryParameters['metadata'] ?? null,
        ];

        $hasPaymentSource = $context->queryParameters['payment_source'];
        if ('0' === $hasPaymentSource) {
            $options['payment_source'] = false;
        } elseif ($hasPaymentSource) {
            $options['payment_source'] = $hasPaymentSource;
        }

        $hasBalance = $context->queryParameters['open_balance'];
        if ('0' === $hasBalance) {
            $options['open_balance'] = false;
        } elseif ($hasBalance) {
            $options['open_balance'] = $hasBalance;
        }

        $hasCreditBalance = $context->queryParameters['balance'];
        if ('0' === $hasCreditBalance) {
            $options['balance'] = false;
        } elseif ($hasCreditBalance) {
            $options['balance'] = $hasCreditBalance;
        }

        $builder = $this->listQueryBuilderFactory->get(
            $this->model::class,
            $this->tenant->get(),
            $options
        );

        try {
            $query = $builder->getBuildQuery($this->perPage);
        } catch (QueryException $e) {
            throw new InvalidRequest($e->getMessage(), 0, $e);
        }

        return $this->addUpdatedFilterToQuery($query);
    }

    //
    // Aging
    //

    /**
     * Adds aging to the customer list response.
     *
     * @param Customer[] $customers
     */
    public function withAging(array $customers): array
    {
        if (0 == count($customers)) {
            return $customers;
        }

        $customerIds = [];
        foreach ($customers as $customer) {
            $customerIds[] = $customer->id();
        }

        $company = $this->tenant->get();
        $agingBreakdown = AgingBreakdown::fromSettings($company->accounts_receivable_settings);
        $aging = new AgingReport($agingBreakdown, $company, $this->database);

        $customerAging = $aging->buildForCustomers($customerIds);

        foreach ($customers as &$customer) {
            $rows = $customerAging[$customer->id()];
            foreach ($rows as &$row) {
                $row['amount'] = $row['amount']->toDecimal();
            }
            $customer->setAging($rows);
        }

        return $customers;
    }
}
