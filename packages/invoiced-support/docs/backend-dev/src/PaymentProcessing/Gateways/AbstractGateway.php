<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Interfaces\OneTimeChargeInterface;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\GatewayLogger;
use App\PaymentProcessing\Libs\PaymentServerClient;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;

/**
 * The base implementation for a typical payment gateway implementation.
 */
abstract class AbstractGateway implements PaymentGatewayInterface, OneTimeChargeInterface, PaymentSourceVaultInterface
{
    const ID = ''; // must be overriden

    public function __construct(
        protected PaymentServerClient $paymentServerClient,
        protected GatewayLogger $gatewayLogger,
        protected RoutingNumberLookup $routingNumberLookup,
        protected PaymentSourceReconciler $sourceReconciler,
    ) {
    }

    public static function getId(): string
    {
        return static::ID;
    }

    //
    // One-Time Charges
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $chargeRequest = [
            'customer_id' => (string) $customer->id,
            'customer_account_number' => $customer->number,
            'description' => $description,
            'level3' => GatewayHelper::makeLevel3($documents, $customer, $amount)->toArray(),
        ];

        if (count($documents) > 0) {
            $chargeRequest['invoice_id'] = (string) $documents[0]->id;
        }

        if (isset($parameters['email'])) {
            $chargeRequest['email'] = $parameters['email'];
        } elseif (isset($parameters['receipt_email'])) {
            $chargeRequest['email'] = $parameters['receipt_email'];
        } elseif ($email = $customer->emailAddress()) {
            $chargeRequest['email'] = $email;
        }

        if (isset($parameters['receipt_email'])) {
            $chargeRequest['receipt_email'] = $parameters['receipt_email'];
        }

        // Charge tokenized payment information
        if (isset($parameters['invoiced_token'])) {
            $chargeRequest['token'] = $parameters['invoiced_token'];
        }

        return $this->paymentServerClient->charge($account, $chargeRequest, $customer, $amount);
    }

    //
    // Payment Sources
    //

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        $sourceRequest = [
            'customer_id' => (string) $customer->id,
            'customer_account_number' => $customer->number,
        ];

        if (isset($parameters['invoiced_token'])) {
            $sourceRequest['token'] = $parameters['invoiced_token'];
        }

        if (isset($parameters['receipt_email'])) {
            $sourceRequest['email'] = $parameters['receipt_email'];
        } elseif ($email = $customer->emailAddress()) {
            $sourceRequest['email'] = $email;
        }

        if (isset($parameters['stripe_customer_id'])) {
            $sourceRequest['stripe_customer_id'] = $parameters['stripe_customer_id'];
        }

        return $this->paymentServerClient->vaultSource($account, $sourceRequest, $customer);
    }

    //
    // Helpers
    //

    public function getPaymentServerClient(): PaymentServerClient
    {
        return $this->paymentServerClient;
    }
}
