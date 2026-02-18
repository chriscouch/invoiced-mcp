<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Contact;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * Edits a customer contact.
 */
class EditContactRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'customer_id' => new RequestParameter(),
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
        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $this->retrieveModel($context);

        $customerId = (int) $context->request->attributes->get('customer_id');
        if ($this->model->customer_id != $customerId) {
            throw $this->modelNotFoundError();
        }

        return parent::buildResponse($context);
    }
}
