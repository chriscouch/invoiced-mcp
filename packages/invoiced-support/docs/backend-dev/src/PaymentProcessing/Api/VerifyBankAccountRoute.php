<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\PaymentProcessing\Exceptions\VerifyBankAccountException;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Operations\VerifyBankAccount;

class VerifyBankAccountRoute extends AbstractRetrieveModelApiRoute
{
    private int $amount1;
    private int $amount2;

    public function __construct(private VerifyBankAccount $verifyBankAccount)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['customers.edit'],
            modelClass: BankAccount::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $this->amount1 = (int) $context->request->request->get('amount1');
        $this->amount2 = (int) $context->request->request->get('amount2');

        $bank = parent::buildResponse($context);

        if (!$bank->needsVerification()) {
            throw new InvalidRequest('This bank account has already been verified.');
        }

        try {
            $this->verifyBankAccount->verify($bank, $this->amount1, $this->amount2);
        } catch (VerifyBankAccountException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $bank;
    }
}
