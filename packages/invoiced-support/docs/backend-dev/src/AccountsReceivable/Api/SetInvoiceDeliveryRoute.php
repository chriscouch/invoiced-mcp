<?php

namespace App\AccountsReceivable\Api;

use App\AccountsReceivable\Models\InvoiceDelivery;
use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Orm\Exception\MassAssignmentException;

class SetInvoiceDeliveryRoute extends AbstractModelApiRoute
{
    private int $invoiceId;
    private bool $updating;

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'emails' => new RequestParameter(
                    types: ['string', 'null']
                ),
                'cadence_id' => new RequestParameter(
                    types: ['int', 'null']
                ),
                'chase_schedule' => new RequestParameter(
                    types: ['array'],
                ),
                'disabled' => new RequestParameter(
                    types: ['bool']
                ),
            ],
            requiredPermissions: ['invoices.edit'],
            modelClass: InvoiceDelivery::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->invoiceId = (int) $context->request->attributes->get('parent_id');

        $delivery = InvoiceDelivery::where('invoice_id', $this->invoiceId)->oneOrNull();

        // determine whether creating or updating model
        if ($delivery instanceof InvoiceDelivery) {
            $this->updating = true;

            $this->setModel($delivery);
            $this->setModelId((string) $delivery->id());
        } else {
            $this->updating = false;
            $requestParameters = $context->requestParameters;
            $requestParameters['invoice'] = $this->invoiceId;
            $context = $context->withRequestParameters($requestParameters);
        }

        try {
            if ($this->updating) {
                // update existing InvoiceDelivery
                if ($this->model->set($context->requestParameters)) {
                    return $this->model;
                }
            } else {
                $parameters = $this->hydrateRelationships($context->requestParameters);

                // create new InvoiceDelivery
                if ($this->model->create($parameters)) {
                    return $this->model;
                }
            }
        } catch (MassAssignmentException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        throw new ApiError('There was an error saving the Invoice Delivery.');
    }
}
