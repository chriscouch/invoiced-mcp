<?php

namespace App\PaymentProcessing\Views\Payment;

use App\Core\I18n\ValueObjects\Money;
use App\Core\Utils\RandomString;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Libs\ChargeApplicationBuilder;
use App\PaymentProcessing\Libs\ConvenienceFeeHelper;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentFlow;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Traits\PaymentFlowUrlTrait;
use App\PaymentProcessing\ValueObjects\ChargeApplication;
use App\PaymentProcessing\ValueObjects\PaymentForm;
use App\PaymentProcessing\ValueObjects\PaymentFormCapabilities;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Renders an Adyen credit card payment form.
 */
class KlarnaPaymentView extends AbstractPaymentView implements LoggerAwareInterface
{
    const array CURRENCIES = [
        'AUD' => [
            'klarna_account',
        ],
        'EUR' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'CAD' => [
            'klarna_account',
        ],
        'CZK' => [
            'klarna_account',
        ],
        'DKK' => [
            'klarna',
            'klarna_account',
        ],
        'NOK' => [
            'klarna',
            'klarna_account',
        ],
        'PLN' => [
            'klarna',
            'klarna_account',
        ],
        'RON' => [
            'klarna_account',
        ],
        'SEK' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'CHF' => [
            'klarna_paynow',
            'klarna',
        ],
        'GBP' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'USD' => [
            'klarna',
            'klarna_account',
        ],
    ];

    const array COUNTRIES = [
        'AU' => [
            'klarna_account',
        ],
        'AT' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'BE' => [
            'klarna_paynow',
            'klarna',
        ],
        'CA' => [
            'klarna_account',
        ],
        'CZ' => [
            'klarna_account',
        ],
        'DK' => [
            'klarna',
            'klarna_account',
        ],
        'FI' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'FR' => [
            'klarna',
            'klarna_account',
        ],
        'DE' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'GR' => [
            'klarna_account',
        ],
        'IE' => [
            'klarna_account',
        ],
        'IT' => [
            'klarna',
            'klarna_account',
        ],
        'NO' => [
            'klarna',
            'klarna_account',
        ],
        'PL' => [
            'klarna',
            'klarna_account',
        ],
        'PT' => [
            'klarna_account',
        ],
        'RO' => [
            'klarna_account',
        ],
        'SP' => [
            'klarna',
            'klarna_account',
        ],
        'SE' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'CH' => [
            'klarna_paynow',
            'klarna',
        ],
        'NL' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'GB' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'UK' => [
            'klarna_paynow',
            'klarna',
            'klarna_account',
        ],
        'US' => [
            'klarna',
            'klarna_account',
        ],
    ];

    use LoggerAwareTrait;
    use PaymentFlowUrlTrait;

    public function __construct(
        Environment $twig,
        TranslatorInterface $translator,
        private AdyenClient $adyen,
        private AdyenGateway $adyenGateway,
        private string $adyenClientKey,
        private bool $adyenLiveMode,
        private UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($twig, $translator);
    }

    public function shouldBeShown(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount): bool
    {
        return $merchantAccount && $this->getAllowedTypes($form) && $merchantAccount->tenant()->features->has('allow_affirm') && $merchantAccount->settings->klarna?->id;
    }

    public function getPaymentFormCapabilities(): PaymentFormCapabilities
    {
        return new PaymentFormCapabilities(
            isSubmittable: true,
            supportsVaulting: false,
            supportsConvenienceFee: false,
            hasReceiptEmail: false,
        );
    }

    protected function getTemplate(): string
    {
        return 'customerPortal/paymentMethods/paymentForms/klarna.twig';
    }

    public function getViewParameters(PaymentForm $form, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, PaymentFlow $paymentFlow): array
    {
        if (!$merchantAccount) {
            return []; // should never be called
        }

        // Apply any convenience fee
        $convenienceFee = ConvenienceFeeHelper::calculate($paymentMethod, $form->customer, $form->totalAmount);
        $amount = $convenienceFee['total'] ?? $form->totalAmount;
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $form->company->country);

        // Retrieve payment methods
        try {
            $paymentMethods = $this->adyen->getPaymentMethods([
                'merchantAccount' => $adyenMerchantAccount,
                'store' => $merchantAccount->gateway_id,
                'amount' => [
                    'currency' => strtoupper($amount->currency),
                    'value' => $amount->amount,
                ],
                'channel' => 'Web',
                'countryCode' => $form->customer->country,
                'shopperLocale' => $form->locale,
            ]);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not get Adyen payment methods', ['exception' => $e]);
            $paymentMethods = [];
        }

        $customer = $form->customer;

        return [
            'scriptUrl' => 'https://checkoutshopper-live.cdn.adyen.com/checkoutshopper/sdk/6.6.0/adyen.js',
            'stylesheetUrl' => 'https://checkoutshopper-live.cdn.adyen.com/checkoutshopper/sdk/6.6.0/adyen.css',
            'adyenData' => [
                'paymentMethods' => $paymentMethods,
                'environment' => AdyenConfiguration::getEnvironment($this->adyenLiveMode),
                'countryCode' => $form->customer->country,
                'locale' => $form->locale,
                'clientKey' => $this->adyenClientKey,
                'transactionData' => $this->makeTransactionData($merchantAccount, $adyenMerchantAccount, $form, $paymentFlow, $amount),
                'customerAddress' => $form->customer->getAddress(),
            ],
            'address' => [
                'email' => $customer->email,
                'name' => $customer->name,
                'address1' => $customer->address1,
                'address2' => $customer->address2,
                'city' => $customer->city,
                'state' => $customer->state,
                'postal_code' => $customer->postal_code,
                'country' => $customer->country ?? $form->company->country,
            ],
            'countries' => $this->getCountries(),
            'allowedTypes' => $this->getAllowedTypes($form),
        ];
    }

    /**
     * This is passed into the POST /payments API call to Adyen.
     */
    private function makeTransactionData(MerchantAccount $merchantAccount, string $adyenMerchantAccount, PaymentForm $form, PaymentFlow $paymentFlow, Money $amount): array
    {
        $adyenAccount = AdyenAccount::one();
        $transactionData = [
            'shopperStatement' => $adyenAccount->getStatementDescriptor(),
            'merchantAccount' => $adyenMerchantAccount,
            'store' => $merchantAccount->gateway_id,
            'reference' => $paymentFlow->identifier,
            'amount' => [
                'currency' => strtoupper($amount->currency),
                'value' => $amount->amount,
            ],
            'countryCode' => $form->customer->country,
            'shopperReference' => $form->customer->client_id ?: RandomString::generate(),
            'shopperLocale' => $form->locale,
            'returnUrl' => $this->getPaymentFlowCompletedUrl($form->company, $paymentFlow),
        ];

        // Add Level 3 data
        $transactionData['additionalData'] = $this->makeLevel3($form, $amount);

        return $transactionData;
    }

    private function makeLevel3(PaymentForm $form, Money $amount): array
    {
        $chargeApplication = $this->buildChargeApplication($form);
        $documents = $chargeApplication->getNonCreditDocuments();

        return $this->adyenGateway->makeLevel3($documents, $form->customer, $amount);
    }

    private function buildChargeApplication(PaymentForm $form): ChargeApplication
    {
        return (new ChargeApplicationBuilder())
            ->addPaymentForm($form)
            ->build();
    }

    private function getAllowedTypes(PaymentForm $form): array
    {
        $country = strtoupper($form->company->country ?? 'US');
        $currency = strtoupper($form->currency);

        return array_fill_keys(array_intersect(self::COUNTRIES[$country] ?? [], self::CURRENCIES[$currency] ?? []),  true);
    }
}
