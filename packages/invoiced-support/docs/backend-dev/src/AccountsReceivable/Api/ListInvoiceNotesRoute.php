<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Note;
use App\Core\RestApi\Routes\AbstractListModelsApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\Query;

/**
 * Lists the notes associated with an invoice.
 */
class ListInvoiceNotesRoute extends AbstractListModelsApiRoute
{
    private int $invoiceId;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Note::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->invoiceId = (int) $context->request->attributes->get('model_id');

        return parent::buildResponse($context);
    }

    public function buildQuery(ApiCallContext $context): Query
    {
        $query = parent::buildQuery($context);

        $query->with('user_id')
            ->where('invoice_id', $this->invoiceId)
            ->sort('id DESC');

        return $query;
    }
}
