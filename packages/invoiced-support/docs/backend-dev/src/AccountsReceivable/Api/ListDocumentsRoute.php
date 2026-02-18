<?php

namespace App\AccountsReceivable\Api;

use App\Core\RestApi\Routes\AbstractListRoutesWithQueryBuilderRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;

abstract class ListDocumentsRoute extends AbstractListRoutesWithQueryBuilderRoute
{
    protected function getOptions(ApiCallContext $context): array
    {
        return [
            'filter' => $this->filter,
            'advanced_filter' => $context->queryParameters['advanced_filter'] ?? null,
            'sort' => $context->queryParameters['sort'] ?? null,
            'start_date' => $context->queryParameters['start_date'] ?? null,
            'end_date' => $context->queryParameters['end_date'] ?? null,
            'automation' => $context->queryParameters['automation'] ?? null,
            'metadata' => $context->queryParameters['metadata'] ?? null,
        ];
    }
}
