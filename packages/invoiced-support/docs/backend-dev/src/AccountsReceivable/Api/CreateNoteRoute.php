<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\Note;
use App\Core\Authentication\Models\User;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractCreateModelApiRoute<Note>
 */
class CreateNoteRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'customer_id' => new RequestParameter(),
                'invoice_id' => new RequestParameter(),
                'user_id' => new RequestParameter(),
                'notes' => new RequestParameter(),
            ],
            requiredPermissions: ['notes.create'],
            modelClass: Note::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;
        if (isset($requestParameters['customer_id'])) {
            $requestParameters['customer'] = $this->getModelOrFail(Customer::class, $requestParameters['customer_id']);
            unset($requestParameters['customer_id']);
        }
        if (isset($requestParameters['invoice_id'])) {
            $requestParameters['invoice'] = $this->getModelOrFail(Invoice::class, $requestParameters['invoice_id']);
            unset($requestParameters['invoice_id']);
        }
        if (isset($requestParameters['user_id'])) {
            $requestParameters['user'] = $this->getModelOrFail(User::class, $requestParameters['user_id']);
            unset($requestParameters['user_id']);
        }
        $context = $context->withRequestParameters($requestParameters);

        return parent::buildResponse($context);
    }
}
