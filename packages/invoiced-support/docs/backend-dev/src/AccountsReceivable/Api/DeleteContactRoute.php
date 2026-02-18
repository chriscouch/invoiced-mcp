<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\Contact;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

class DeleteContactRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
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
