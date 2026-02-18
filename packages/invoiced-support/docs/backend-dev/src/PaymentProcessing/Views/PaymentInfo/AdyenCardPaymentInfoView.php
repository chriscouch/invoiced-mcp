<?php

namespace App\PaymentProcessing\Views\PaymentInfo;

use App\AccountsReceivable\Models\Customer;
use App\Companies\Models\Company;
use App\Core\Utils\RandomString;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\TokenizationFlow;
use App\PaymentProcessing\Traits\PaymentFlowUrlTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * Handles card update payment information forms
 * for the Adyen payment gateway.
 */
class AdyenCardPaymentInfoView extends AbstractPaymentInfoView implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use PaymentFlowUrlTrait;

    public function __construct(
        Environment $twig,
        private AdyenClient $adyen,
        private string $adyenClientKey,
        private bool $adyenLiveMode,
        private LocaleSwitcher $localeSwitcher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($twig);
    }

    public function shouldBeShown(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, ?Customer $customer): bool
    {
        return null != $merchantAccount;
    }

    public function render(Company $company, PaymentMethod $paymentMethod, ?MerchantAccount $merchantAccount, TokenizationFlow $flow): string
    {
        if (!$merchantAccount) {
            return ''; // should never be called
        }

        $customer = $flow->customer;
        $country = $customer?->country ?? $company->country;
        $locale = $this->localeSwitcher->getLocale();
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $company->country);

        try {
            $paymentMethods = $this->adyen->getPaymentMethods([
                'merchantAccount' => $adyenMerchantAccount,
                'store' => $merchantAccount->gateway_id,
                'amount' => [
                    'currency' => strtoupper($company->currency),
                    'value' => 0,
                ],
                'channel' => 'Web',
                'countryCode' => $country,
                'shopperLocale' => $locale,
                'allowedPaymentMethods' => ['amex', 'cup', 'discover', 'jcb', 'maestro', 'mc', 'visa', 'applepay', 'googlepay'],
            ]);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not get Adyen payment methods', ['exception' => $e]);
            $paymentMethods = [];
        }

        $tokenizationCompleteUrl = $this->getTokenizationCompletedUrl($company, $flow);

        return $this->twig->render(
            'customerPortal/paymentMethods/paymentInfoForms/cardAdyen.twig',
            [
                'scriptUrl' => 'https://checkoutshopper-live.cdn.adyen.com/checkoutshopper/sdk/6.6.0/adyen.js',
                'stylesheetUrl' => 'https://checkoutshopper-live.cdn.adyen.com/checkoutshopper/sdk/6.6.0/adyen.css',
                'adyenData' => [
                    'paymentMethods' => $paymentMethods,
                    'environment' => AdyenConfiguration::getEnvironment($this->adyenLiveMode),
                    'countryCode' => $country,
                    'locale' => $locale,
                    'clientKey' => $this->adyenClientKey,
                    'transactionData' => [
                        'merchantAccount' => $adyenMerchantAccount,
                        'store' => $merchantAccount->gateway_id,
                        'reference' => $flow->identifier,
                        'amount' => [
                            'currency' => strtoupper($company->currency),
                            'value' => 0,
                        ],
                        'countryCode' => $country,
                        // We generate a new shopper reference with each stored card because
                        // Adyen does not permit the same card to be stored for the same shopper.
                        'shopperReference' => RandomString::generate(16, RandomString::CHAR_NUMERIC.RandomString::CHAR_UPPER),
                        'shopperInteraction' => 'Ecommerce',
                        'recurringProcessingModel' => 'UnscheduledCardOnFile',
                        'storePaymentMethod' => true,
                        'shopperLocale' => $locale,
                        'returnUrl' => $tokenizationCompleteUrl,
                    ],
                    'customerAddress' => $flow->customer?->getAddress() ?? [],
                ],
            ]
        );
    }
}
