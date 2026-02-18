<?php

namespace App\PaymentProcessing\Operations;

use App\Core\Orm\Exception\ModelException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\VerifyBankAccountException;
use App\PaymentProcessing\Gateways\PaymentGatewayFactory;
use App\PaymentProcessing\Interfaces\VerifyBankAccountInterface;
use App\PaymentProcessing\Models\BankAccount;

/**
 * Simple interface for verifying bank accounts that handles
 * routing to the appropriate gateway and reconciliation.
 */
class VerifyBankAccount
{
    public function __construct(private PaymentGatewayFactory $gatewayFactory)
    {
    }

    /**
     * Saves verified bank account.
     *
     * @throws VerifyBankAccountException
     */
    public function verify(BankAccount $bank, int $amount1, int $amount2): void
    {
        $merchantAccount = $bank->getMerchantAccount();

        try {
            $gateway = $this->gatewayFactory->get($bank->gateway);
            $gateway->validateConfiguration($merchantAccount->toGatewayConfiguration());
        } catch (InvalidGatewayConfigurationException $e) {
            throw new VerifyBankAccountException($e->getMessage());
        }

        if (!$gateway instanceof VerifyBankAccountInterface) {
            throw new VerifyBankAccountException("The `{$bank->gateway}` payment gateway does not support verifying bank accounts");
        }

        // verify the bank account through the gateway
        $gateway->verifyBankAccount($merchantAccount, $bank, $amount1, $amount2);

        // update the verification status
        try {
            $bank->verified = true;
            $bank->saveOrFail();
        } catch (ModelException $e) {
            throw new VerifyBankAccountException($e->getMessage());
        }
    }
}
