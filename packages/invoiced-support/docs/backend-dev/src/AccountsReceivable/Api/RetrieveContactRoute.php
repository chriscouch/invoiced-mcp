<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Contact;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class RetrieveContactRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Contact::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $customerId = (int) $context->request->attributes->get('customer_id');

        $contact = parent::buildResponse($context);

        if ($contact->customer_id != $customerId) {
            throw $this->modelNotFoundError();
        }

        return $contact;
    }
}
