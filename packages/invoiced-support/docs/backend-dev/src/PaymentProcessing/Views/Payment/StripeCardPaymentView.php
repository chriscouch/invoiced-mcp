<?php

namespace App\PaymentProcessing\Views\Payment;

use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Gateways\StripeGateway;
use App\PaymentProcessing\Libs\ChargeApplicationBuilder;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Renders a Stripe credit card payment form.
 */
class StripeCardPaymentView extends CardPaymentView
{
    public function __construct(
        Environment $twig,
        TranslatorInterface $translator,
        private StripeGateway $stripeGateway,
    ) {
        parent::__construct($twig, $translator);
    }

    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        $credentials = $merchantAccount?->credentials;

        return (bool) ($credentials?->key ?? '');
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            supportsConvenienceFee: true,
            hasReceiptEmail: true
        );
    }

    protected function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/cardStripe.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        if (!$merchantAccount) {
            return []; // should never be called
        }
        $credentials = $merchantAccount->credentials;
        $publishableKey = $credentials->publishable_key ?? null;

        // Apply any convenience fee
        $convenienceFee = ConvenienceFeeHelper::calculate($paymentMethod, $form->customer, $form->totalAmount);
        $amount = $convenienceFee['total'] ?? $form->totalAmount;

        $chargeApplication = $this->buildChargeApplication($form);
        $documents = $chargeApplication->getNonCreditDocuments();

        try {
            $clientSecret = $this->stripeGateway->createPaymentIntent($merchantAccount, $paymentFlow, $amount, $documents, ['card']);
        } catch (ChargeException) {
            // do nothing
            $clientSecret = null;
        }

        $defaultValues = null;
        if ($customer = $paymentFlow->customer) {
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

        return [
            'publishableKey' => $publishableKey,
            'clientSecret' => $clientSecret,
            'defaultValues' => $defaultValues,
        ];
    }

    private function buildChargeApplication(PaymentForm $form): ChargeApplication
    {
        return (new ChargeApplicationBuilder())
            ->addPaymentForm($form)
            ->build();
    }
}
