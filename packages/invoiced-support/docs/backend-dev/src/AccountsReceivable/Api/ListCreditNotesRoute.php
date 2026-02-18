<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Request;

class ListCreditNotesRoute extends ListDocumentsRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: CreditNote::class,
            filterableProperties: ['network_document'],
            features: ['accounts_receivable'],
        );
    }

    public function parseListParameters(Request $request): void
    {
        parent::parseListParameters($request);

        // rewrite `invoice` filter to `invoice_id`
        if (isset($this->filter['invoice'])) {
            $this->filter['invoice_id'] = $this->filter['invoice'];
            unset($this->filter['invoice']);
        }
    }
}
