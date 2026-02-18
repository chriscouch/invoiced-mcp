<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * Creates a new customer contact.
 */
class CreateContactRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'title' => new RequestParameter(),
                'department' => new RequestParameter(),
                'email' => new RequestParameter(),
                'primary' => new RequestParameter(),
                'phone' => new RequestParameter(),
                'sms_enabled' => new RequestParameter(),
                'send_new_invoices' => new RequestParameter(),
                'address1' => new RequestParameter(),
                'address2' => new RequestParameter(),
                'city' => new RequestParameter(),
                'state' => new RequestParameter(),
                'postal_code' => new RequestParameter(),
                'country' => new RequestParameter(),
                'role' => new RequestParameter(),
            ],
            requiredPermissions: ['customers.edit'],
            modelClass: Contact::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;
        $requestParameters['customer'] = $this->getModelOrFail(Customer::class, $context->request->attributes->get('customer_id'));
        $context = $context->withRequestParameters($requestParameters);

        return parent::buildResponse($context);
    }
}
