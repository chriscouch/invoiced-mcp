<?php

namespace App\PaymentProcessing\Views\Payment;

use App\Core\Authentication\Libs\UserContext;
use App\Core\I18n\Countries;
use App\Core\Utils\RandomString;
use App\Integrations\Flywire\FlywireHelper;
use App\Integrations\Flywire\Traits\FlywireTrait;
use App\PaymentProcessing\Gateways\FlywireGateway;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;
use App\Sending\Sms\Libs\TextMessageSender;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Renders a Flywire payment form.
 */
class FlywirePaymentView extends AbstractPaymentView
{
    use FlywireTrait;

    public function __construct(
        private string $environment,
        private UrlGeneratorInterface $urlGenerator,
        Environment $twig,
        TranslatorInterface $translator,
        private UserContext $userContext,
        private readonly TextMessageSender $messageSender,
        private readonly FlywireGateway $flywireGateway,
    ) {
        parent::__construct($twig, $translator);
    }

    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        // There must be a portal code for the requested currency in order
        // to show the payment form.
        if (!$merchantAccount) {
            return false;
        }

        if (!property_exists($merchantAccount->credentials, 'shared_secret') || !$merchantAccount->credentials->shared_secret) {
            return false;
        }

        $portalCode = FlywireHelper::getPortalCodeForCurrency($merchantAccount, $form->currency);

        return (bool) $portalCode;
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: true,
            // this should be disabled until INV-126 fixed
            supportsConvenienceFee: false,
            hasReceiptEmail: false
        );
    }

    protected function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/flywire.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        if (!$merchantAccount) {
            return []; // should never be called
        }
        $convenienceFee = ConvenienceFeeHelper::calculate($paymentMethod, $form->customer, $form->totalAmount);

        $customer = $form->customer;
        $portalCode = FlywireHelper::getPortalCodeForCurrency($merchantAccount, $form->currency);
        $user = $this->userContext->get();
        $locale = $form->locale;
        $locale = explode('_', $locale)[0]; // Flywire only accepts language
        $country = $customer->country ?? $customer->tenant()->country ?? 'US';

        $hasSurcharging = $customer->tenant()->features->has('flywire_surcharging');
        $hasConvenienceFee = !$hasSurcharging && $customer->convenience_fee;

        $level3 = $this->flywireGateway->makeLevel3($merchantAccount, $customer, $form->totalAmount, $form->documents);

        return [
            'jsonId' => RandomString::generate(),
            'paymentMethodValues' => [
                'paymentMethod' => $paymentMethod->id,
                'type' => 'flywire_payment',
                'config' => [
                    'environment' => 'production' === $this->environment ? 'prod' : 'demo',
                    'code' => $portalCode,
                    'amount' => ($hasConvenienceFee ? $convenienceFee['total']?->toDecimal() : null) ?? $form->totalAmount->toDecimal(),
                    'email' => $customer->email,
                    'phone' => $this->messageSender->getPhoneNumber($customer->phone, $country),
                    'firstName' => $user?->first_name,
                    'lastName' => $user?->last_name,
                    'address' => $customer->address1.($customer->address2 ? ', '.$customer->address2 : ''),
                    'city' => $customer->city,
                    'state' => (new Countries())->getStateShortName($customer->state, $country),
                    'zip' => $customer->postal_code,
                    'country' => $customer->country,
                    'locale' => $locale,
                    'nonce' => $paymentFlow->identifier,
                    'convenienceFee' => $hasConvenienceFee,
                    'surcharging' => $hasSurcharging && $customer->surcharging,
                    'callbackUrl' => $this->paymentCallbackUrl($merchantAccount),
                    'callbackId' => $paymentFlow->identifier,
                    'alert' => $this->translator->trans('messages.flywire_alert', [], 'customer_portal'),
                    'filters' => $this->buildFilters($paymentMethod->id, $form->methods),
                    'sort' => $this->buildSort($paymentMethod->id),
                    'customer' => [
                        'metadata' => $customer->metadata,
                        'name' => $customer->name,
                        'number' => $customer->number,
                    ],
                    'documents' => array_map(fn ($document) => [
                        'metadata' => $document->metadata,
                        'name' => $document->name,
                        'number' => $document->number,
                        'type' => $document->object,
                    ], $form->documents),
                    'additionalData' => $level3,
                ],
            ],
        ];
    }
}
