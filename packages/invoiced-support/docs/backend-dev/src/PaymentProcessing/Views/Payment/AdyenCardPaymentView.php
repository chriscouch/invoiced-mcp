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
class AdyenCardPaymentView extends AbstractPaymentView implements LoggerAwareInterface
{
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
        return null != $merchantAccount;
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
        return 'customerPortal/paymentMethods/paymentForms/cardAdyen.twig';
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
                'allowedPaymentMethods' => ['amex', 'cup', 'discover', 'jcb', 'maestro', 'mc', 'visa', 'applepay', 'googlepay'],
            ]);
        } catch (IntegrationApiException $e) {
            $this->logger->error('Could not get Adyen payment methods', ['exception' => $e]);
            $paymentMethods = [];
        }

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
}
