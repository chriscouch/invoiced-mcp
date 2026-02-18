<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\CreditNote;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Traits\AccountingApiParametersTrait;

/**
 * @extends AbstractEditModelApiRoute<CreditNote>
 */
class EditCreditNoteRoute extends AbstractEditModelApiRoute
{
    use AccountingApiParametersTrait;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: null,
            requiredPermissions: ['credit_notes.edit'],
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
