<?php

namespace App\PaymentProcessing\Libs;

use App\AccountsReceivable\Models\Customer;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\DeletePaymentInfo;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;

class ImportPaymentSource
{
    private const OBJECT_CARD = 'card';
    private const OBJECT_BANK_ACCOUNT = 'bank_account';

    /** @var MerchantAccount[] */
    private array $merchantAccountsById = [];
    /** @var MerchantAccount[] */
    private array $merchantAccountsByGateway = [];

    public function __construct(
        private PaymentSourceReconciler $reconciler,
        private DeletePaymentInfo $deletePaymentInfo
    ) {
    }

    /**
     * Imports a payment source into the payment system that is a reference
     * to a payment source already tokenized on a payment gateway.
     *
     * @throws PaymentSourceException
     */
    public function import(Customer $customer, array $params, MerchantAccount $merchantAccount): PaymentSource
    {
        // set bank account preset values
        $type = $params['type'] ?? null;
        unset($params['type']);
        if (!in_array($type, [self::OBJECT_CARD, self::OBJECT_BANK_ACCOUNT])) {
            throw new PaymentSourceException("Invalid source type. Allowed values are 'card', 'bank_account'");
        }

        // create the source value object
        $source = $this->buildSource($customer, $merchantAccount, $type, $params);

        // reconcile the payment source locally
        try {
            $paymentSource = $this->reconciler->reconcile($source);
        } catch (ReconciliationException $e) {
            throw new PaymentSourceException($e->getMessage());
        }

        // make it the default for customer, if there is not already a default
        if (!$customer->payment_source) {
            $customer->setDefaultPaymentSource($paymentSource, $this->deletePaymentInfo);
        }

        return $paymentSource;
    }

    /**
     * @throws PaymentSourceException
     */
    public function getMerchantAccountForId(string $id): MerchantAccount
    {
        if (isset($this->merchantAccountsById[$id])) {
            return $this->merchantAccountsById[$id];
        }

        $merchantAccount = MerchantAccount::withoutDeleted()
            ->where('id', $id)
            ->oneOrNull();
        if (!$merchantAccount) {
            throw new PaymentSourceException('Could not find payment gateway configuration: '.$id);
        }

        $this->merchantAccountsById[$id] = $merchantAccount;

        return $merchantAccount;
    }

    /**
     * @throws PaymentSourceException
     */
    public function getMerchantAccountForGateway(string $gateway): MerchantAccount
    {
        if (isset($this->merchantAccountsByGateway[$gateway])) {
            return $this->merchantAccountsByGateway[$gateway];
        }

        $merchantAccounts = MerchantAccount::withoutDeleted()
            ->where('gateway', $gateway)
            ->first(2);

        if (0 == count($merchantAccounts)) {
            throw new PaymentSourceException('Gateway configuration does not exist for gateway: '.$gateway);
        } elseif (count($merchantAccounts) > 1) {
            throw new PaymentSourceException('Multiple active gateway configurations exist for gateway: '.$gateway);
        }

        $this->merchantAccountsByGateway[$gateway] = $merchantAccounts[0];

        return $merchantAccounts[0];
    }

    /**
     * @throws PaymentSourceException
     */
    private function buildSource(Customer $customer, MerchantAccount $merchantAccount, string $type, array $source): SourceValueObject
    {
        if (self::OBJECT_CARD == $type) {
            return new CardValueObject(
                customer: $customer,
                gateway: $merchantAccount->gateway,
                gatewayId: $source['gateway_id'] ?? null,
                gatewayCustomer: $source['gateway_customer'] ?? null,
                gatewaySetupIntent: $source['gateway_setup_intent'] ?? null,
                merchantAccount: $merchantAccount,
                chargeable: true,
                receiptEmail: $source['receipt_email'] ?? null,
                brand: $source['brand'] ?? 'Unknown',
                funding: $source['funding'] ?? 'unknown',
                last4: $source['last4'] ?? '0000',
                expMonth: (int) $source['exp_month'],
                expYear: (int) $source['exp_year'],
                country: $source['issuing_country'] ?? null,
            );
        }

        if (self::OBJECT_BANK_ACCOUNT == $type) {
            return new BankAccountValueObject(
                customer: $customer,
                gateway: $merchantAccount->gateway,
                gatewayId: $source['gateway_id'] ?? null,
                gatewayCustomer: $source['gateway_customer'] ?? null,
                gatewaySetupIntent: $source['gateway_setup_intent'] ?? null,
                merchantAccount: $merchantAccount,
                chargeable: true,
                receiptEmail: $source['receipt_email'] ?? null,
                bankName: $source['bank_name'] ?? 'Unknown',
                routingNumber: $source['routing_number'] ?? null,
                accountNumber: $source['account_number'] ?? null,
                last4: $source['last4'] ?? '0000',
                currency: $source['currency'] ?? 'USD',
                country: $source['country'] ?? 'US',
                accountHolderName: $source['account_holder_name'] ?? $customer->name,
                accountHolderType: $source['account_holder_type'] ?? null,
                type: $source['account_type'] ?? null,
                verified: true,
            );
        }

        throw new PaymentSourceException('Unsupported source type: '.$type);
    }
}
