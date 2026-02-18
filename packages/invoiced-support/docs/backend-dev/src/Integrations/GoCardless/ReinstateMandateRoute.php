<?php

namespace App\Integrations\GoCardless;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Gateways\GoCardlessGateway;
use App\PaymentProcessing\Models\BankAccount;
use GoCardlessPro\Core\Exception\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reinstates cancelled mandates on GoCardless.
 */
class ReinstateMandateRoute extends AbstractRetrieveModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['customers.edit'],
            modelClass: BankAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $customerId = (int) $context->request->attributes->get('customer_id');

        parent::buildResponse($context);

        if ($this->model->customer_id != $customerId) {
            throw $this->modelNotFoundError();
        }

        if (GoCardlessGateway::ID != $this->model->gateway) {
            throw new InvalidRequest('This payment source does not support reinstating mandates.');
        }

        $customer = Customer::findOrFail($customerId);

        $this->reinstate($this->model->gateway_id);

        return new Response('', 204);
    }

    public function reinstate(string $mandateId): void
    {
        $api = new GoCardlessApi();
        $client = $api->getClient($this->model->getMerchantAccount());

        try {
            $client->mandates()->reinstate($mandateId);
        } catch (ApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
