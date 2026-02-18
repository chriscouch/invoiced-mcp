<?php

namespace App\Tokenization\Operations;

use App\Companies\Models\Company;
use App\Core\Utils\RandomString;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\PaymentProcessing\Gateways\AdyenGateway;
use App\PaymentProcessing\Gateways\TestGateway;
use App\PaymentProcessing\Models\MerchantAccount;
use App\Tokenization\Models\TokenizationApplication;

abstract class Tokenize
{
    protected string $reference;
    protected ?MerchantAccount $merchantAccount = null;
    protected string $gateway;

    public function __construct(private readonly Company $tenant, private readonly bool $adyenLiveMode)
    {
        $this->reference = RandomString::generate(28, RandomString::CHAR_ALNUM);

        $this->merchantAccount = MerchantAccount::withoutDeleted()
            ->where('gateway', AdyenGateway::ID)
            ->oneOrNull();
        $this->gateway = $this->merchantAccount ? AdyenGateway::ID : TestGateway::ID;
    }

    abstract public function getParameters(array $data): array;

    public function makeApplication(array $data, array $input): TokenizationApplication
    {
        $application = new TokenizationApplication();
        $application->identifier = $this->reference;
        $application->merchant_account = $this->merchantAccount;
        $application->gateway = $this->gateway;

        return $application;
    }


    public function getBaseParameters(): array
    {
        /** @var AdyenAccount $adyenAccount */
        $adyenAccount = AdyenAccount::one();
        $data = [
            "amount" => [
                "currency" => strtoupper($this->tenant->currency),
                "value" => 0,
            ],
            "reference" => $this->reference,
            "merchantAccount" => AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, $this->tenant->country ?? 'US'),
            "recurringProcessingModel" => "UnscheduledCardOnFile",
            "storePaymentMethod" => true,
            "shopperInteraction" => "Ecommerce",
            'shopperReference' => RandomString::generate(32, RandomString::CHAR_ALNUM),
            'shopperStatement' => $adyenAccount->getStatementDescriptor(),
        ];

        if ($this->merchantAccount) {
            $data['store'] = $this->merchantAccount->gateway_id;
        }

        return $data;
    }
}
