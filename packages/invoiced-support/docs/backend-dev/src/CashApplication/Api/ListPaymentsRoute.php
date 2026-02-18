<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\Routes\AbstractListRoutesWithQueryBuilderRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\Orm\Query;

class ListPaymentsRoute extends AbstractListRoutesWithQueryBuilderRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: array_merge(
                $this->getBaseQueryParameters(),
                $this->getUpdatedQueryParameters(),
                [
                    'balance' => new QueryParameter(
                        types: ['string']
                    ),
                    'amount' => new QueryParameter(
                        types: ['string']
                    ),
                    'start_date' => new QueryParameter(
                        types: ['numeric'],
                    ),
                    'end_date' => new QueryParameter(
                        types: ['numeric'],
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
            modelClass: Payment::class,
            features: ['accounts_receivable'],
        );
    }

    protected function getOptions(ApiCallContext $context): array
    {
        return [
            'filter' => $this->filter,
            'advanced_filter' => $context->queryParameters['advanced_filter'] ?? null,
            'sort' => $context->queryParameters['sort'] ?? null,
            'start_date' => $context->queryParameters['start_date'] ?? null,
            'end_date' => $context->queryParameters['end_date'] ?? null,
            'automation' => $context->queryParameters['automation'] ?? null,
            'amount' => $context->queryParameters['amount'] ?? null,
            'balance' => $context->queryParameters['balance'] ?? null,
            'metadata' => $context->queryParameters['metadata'] ?? null,
        ];
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        if ($this->isParameterIncluded($context, 'bankAccountName')) {
            $query->with('plaid_bank_account_id');
        }

        // eager load if including customerName property (dashboard only)
        if ($this->isParameterIncluded($context, 'customerName')) {
            $query->with('customer');
        }

        return $query;
    }
}
