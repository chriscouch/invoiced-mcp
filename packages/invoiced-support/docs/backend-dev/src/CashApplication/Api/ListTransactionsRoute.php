<?php

namespace App\CashApplication\Api;

use App\CashApplication\Models\Transaction;
use App\Core\RestApi\Routes\AbstractListRoutesWithQueryBuilderRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Request;

class ListTransactionsRoute extends AbstractListRoutesWithQueryBuilderRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Transaction::class,
            features: ['accounts_receivable'],
        );
    }

    public function parseListParameters(Request $request): void
    {
        parent::parseListParameters($request);

        // rewrite `credit_note` filter to `credit_note_id`
        if (isset($this->filter['credit_note'])) {
            $this->filter['credit_note_id'] = $this->filter['credit_note'];
            unset($this->filter['credit_note']);
        }

        // rewrite `estimate` filter to `estimate_id`
        if (isset($this->filter['estimate'])) {
            $this->filter['estimate_id'] = $this->filter['estimate'];
            unset($this->filter['estimate']);
        }
    }

    protected function getOptions(ApiCallContext $context): array
    {
        return [
            'filter' => $this->filter,
            'advanced_filter' => $context->queryParameters['advanced_filter'] ?? null,
            'sort' => $context->queryParameters['sort'] ?? null,
            'start_date' => $context->queryParameters['start_date'] ?? null,
            'end_date' => $context->queryParameters['end_date'] ?? null,
            'amount' => $context->queryParameters['amount'] ?? null,
            'metadata' => $context->queryParameters['metadata'] ?? null,
        ];
    }
}
