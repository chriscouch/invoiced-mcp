<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Twig\Environment;

/**
 * Handles ACH update payment information forms
 * for the Stripe payment gateway.
 */
class StripeCardPaymentInfoView extends AbstractPaymentInfoView implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        Environment $twig,
        private StripeGateway $stripeGateway,
    ) {
        parent::__construct($twig);
    }

    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool
    {
        $credentials = $merchantAccount?->credentials;

        return (bool) ($credentials?->key ?? '');
    }

    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string
    {
        if (!$merchantAccount) {
            return ''; // should never be called
        }

        $credentials = $merchantAccount->credentials;
        $publishableKey = $credentials->publishable_key ?? null;
        $customer = $flow->customer;

        try {
            $clientSecret = $this->stripeGateway->createSetupIntent($merchantAccount, $customer, $flow, ['card']);
        } catch (PaymentSourceException) {
            // do nothing
            $clientSecret = null;
        }

        $defaultValues = null;
        if ($customer) {
            $defaultValues = [
                'billingDetails' => [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'address' => [
                        'line1' => $customer->address1,
                        'line2' => $customer->address2,
                        'city' => $customer->city,
                        'state' => $customer->state,
                        'postal_code' => $customer->postal_code,
                        'country' => $customer->country,
                    ],
                ],
            ];
        }

        return $this->twig->render(
            'customerPortal/paymentMethods/paymentInfoForms/cardStripe.twig',
            [
                'publishableKey' => $publishableKey,
                'clientSecret' => $clientSecret,
                'defaultValues' => $defaultValues,
            ]
        );
    }
}
