<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\I18n\Countries;
use App\Integrations\Flywire\FlywireHelper;
use App\Integrations\Flywire\Traits\FlywireTrait;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Renders a payment source form for Flywire.
 */
class FlywirePaymentInfoView extends AbstractPaymentInfoView
{
    use FlywireTrait;

    public function __construct(
        private readonly string $environment,
        private UrlGeneratorInterface $urlGenerator,
        Environment $twig,
        private UserContext $userContext,
        private TranslatorInterface $translator,
        private LocaleSwitcher $localeSwitcher,
    ) {
        parent::__construct($twig);
    }

    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool
    {
        // Only card or direct debit can be tokenized
        if (!in_array($paymentMethod->id, [PaymentMethodType::Card->toString(), PaymentMethodType::DirectDebit->toString()])) {
            return false;
        }

        // There must be a portal code for the requested currency in order
        // to show the payment form.
        if (!$merchantAccount) {
            return false;
        }

        if (!property_exists($merchantAccount->credentials, 'shared_secret') || !$merchantAccount->credentials->shared_secret) {
            return false;
        }

        $currency = $customer?->currency ?? $company->currency;
        $portalCode = FlywireHelper::getPortalCodeForCurrency($merchantAccount, $currency);

        return (bool) $portalCode;
    }

    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string
    {
        if (!$merchantAccount) {
            return ''; // should never be called
        }

        return $this->twig->render(
            'customerPortal/paymentMethods/paymentInfoForms/flywire.twig',
            $this->getViewParameters($company, $paymentMethod, $merchantAccount, $flow)
        );
    }

    public function getViewParameters(Company $company, PaymentMethod $paymentMethod, MerchantAccount $merchantAccount, TokenizationFlow $flow): array
    {
        $customer = $flow->customer;
        $portalCode = FlywireHelper::getPortalCodeForCurrency($merchantAccount, $customer?->currency ?? $company->currency);
        $user = $this->userContext->get();
        $locale = $this->localeSwitcher->getLocale();
        $locale = explode('_', $locale)[0]; // Flywire only accepts language

        return [
            'jsonId' => $flow->identifier,
            'paymentMethodValues' => [
                'paymentMethod' => $paymentMethod->id,
                'type' => 'flywire_payment',
                'config' => [
                    'environment' => 'production' === $this->environment ? 'prod' : 'demo',
                    'code' => $portalCode,
                    'email' => $customer?->email,
                    'phone' => $customer?->phone,
                    'firstName' => $user?->first_name,
                    'lastName' => $user?->last_name,
                    'address' => $customer ? $customer->address1.($customer->address2 ? ', '.$customer->address2 : '') : null,
                    'city' => $customer?->city,
                    'state' => (new Countries())->getStateShortName($customer?->state, $customer?->country),
                    'zip' => $customer?->postal_code,
                    'country' => $customer?->country,
                    'locale' => $locale,
                    'alert' => $this->translator->trans('messages.flywire_alert', [], 'customer_portal'),
                    // When building the filters, only the currently selected payment method
                    // will be available because we do not know the other payment methods enabled
                    // on the page.
                    'filters' => $this->buildFilters($paymentMethod->id, []),
                    'sort' => $this->buildSort($paymentMethod->id, true),
                ],
            ],
        ];
    }
}
