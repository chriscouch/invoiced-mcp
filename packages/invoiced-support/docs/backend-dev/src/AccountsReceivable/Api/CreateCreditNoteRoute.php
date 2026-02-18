<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;

class CreateCreditNoteRoute extends AbstractCreateModelApiRoute
{
    use AccountingApiParametersTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['credit_notes.create'],
            modelClass: CreditNote::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->parseRequestAccountingParameters($context);

        /** @var CreditNote $creditNote */
        $creditNote = parent::buildResponse($context);
        $this->createAccountingMapping($creditNote);

        return $creditNote;
    }
}
