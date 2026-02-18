<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use Symfony\Component\HttpFoundation\Response;

class DeletePaymentSourceRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(private DeletePaymentInfo $deletePaymentInfo)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: null,
            requiredPermissions: ['customers.edit'],
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $type = $context->request->attributes->get('source_type');
        if ('cards' == $type) {
            $this->setModelClass(Card::class);
        } elseif ('bank_accounts' == $type) {
            $this->setModelClass(BankAccount::class);
        }

        if (!$this->getModelId()) {
            $this->setModelId($context->request->attributes->get('model_id'));
        }

        $this->retrieveModel($context);

        $customerId = (int) $context->request->attributes->get('customer_id');
        if ($this->model->customer_id != $customerId) {
            throw $this->modelNotFoundError();
        }

        try {
            $this->deletePaymentInfo->delete($this->model);
        } catch (PaymentSourceException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return new Response('', 204);
    }
}
