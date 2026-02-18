<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\Core\I18n\Countries;
use App\Core\I18n\ValueObjects\Money;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\Utils\Enums\ObjectType;
use App\Core\Utils\RandomString;
use App\Integrations\Adyen\AdyenClient;
use App\Integrations\Adyen\AdyenConfiguration;
use App\Integrations\Adyen\AdyenPricingEngine;
use App\Integrations\Adyen\Models\AdyenAccount;
use App\Integrations\Adyen\Models\AdyenPaymentResult;
use App\Integrations\Adyen\Operations\SaveAlreadyExistingPaymentSource;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\Enums\FlywirePaymentStatus;
use App\PaymentProcessing\Enums\MerchantAccountTransactionType;
use App\PaymentProcessing\Enums\PaymentMethodType;
use App\PaymentProcessing\Exceptions\AdyenCardException;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\DisputeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Interfaces\OneTimeChargeInterface;
use App\PaymentProcessing\Interfaces\PaymentGatewayInterface;
use App\PaymentProcessing\Interfaces\PaymentSourceVaultInterface;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TransactionStatusInterface;
use App\PaymentProcessing\Libs\BankAccountValidator;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\RoutingNumberLookup;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\Charge;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\MerchantAccountTransaction;
use App\PaymentProcessing\Models\PaymentMethod;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\Operations\PaymentFlowReconcile;
use App\PaymentProcessing\Reconciliation\PaymentSourceReconciler;
use App\PaymentProcessing\ValueObjects\BankAccountValueObject;
use App\PaymentProcessing\ValueObjects\CardValueObject;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentFlowReconcileData;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use App\Tokenization\Traits\InvoicedTokenizationTrait;
use Carbon\CarbonImmutable;
use Throwable;

class AdyenGateway implements PaymentGatewayInterface, OneTimeChargeInterface, PaymentSourceVaultInterface, RefundInterface, TransactionStatusInterface
{
    const string ID = 'flywire_payments';

    private const string AUTHORIZED = 'Authorised';

    use InvoicedTokenizationTrait;

    public function __construct(
        private readonly AdyenClient             $adyen,
        private readonly PaymentSourceReconciler $sourceReconciler,
        private readonly RoutingNumberLookup     $routingNumberLookup,
        private readonly PaymentFlowReconcile    $paymentFlowReconcile,
        private readonly bool                    $adyenLiveMode,
        private readonly SaveAlreadyExistingPaymentSource $saveAlreadyExistingPaymentSource,
    ) {
    }

    public static function getId(): string
    {
        return static::ID;
    }

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
    }

    //
    // OneTimeChargeInterface
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            return $this->performChargeAchForm($customer, $account, $amount, $description, $parameters);
        }

        if ('flywire_payments' == $paymentMethod) {
            return $this->performChargeFlywirePaymentForm($customer, $account, $amount, $description, $parameters);
        }

        if ('credit_card' == $paymentMethod or 'card' == $paymentMethod) {
            $charge = $this->buildChargeCardReference($customer, $account, $amount, $description, $parameters);
            $this->saveAlreadyExistingPaymentSource->process($account, $customer, $parameters['reference']);

            return $charge;
        }

        throw new ChargeException('Unsupported payment method');
    }

    //
    // PaymentSourceVaultInterface
    //

    /**
     * @throws AdyenCardException
     * @throws IntegrationApiException
     * @throws PaymentSourceException
     * @throws \App\Core\Multitenant\Exception\MultitenantException
     * @throws \App\Core\Orm\Exception\ModelNotFoundException
     */
    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        if (isset($parameters['invoiced_token'])) {
            return $this->vaultInvoicedSource($parameters['invoiced_token'], $customer, $account);
        }

        // We store ACH bank accounts in our database. It is not tokenized on Adyen.
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            try {
                $adyenData = [
                    'merchantAccount' => AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string)$customer->tenant()->country),
                    'amount' => [
                        'currency' => 'USD',
                        'value' => 0,
                    ],
                    'reference' => RandomString::generate(32, RandomString::CHAR_LOWER . RandomString::CHAR_NUMERIC),
                    'paymentMethod' => [
                        'type' => 'ach',
                        'bankAccountNumber' => $parameters['account_number'],
                        'bankAccountType' => 'checking',
                        'bankLocationId' => $parameters['routing_number'],
                        'ownerName' => $parameters['account_holder_name'],
                    ],
                    "recurringProcessingModel" => "Subscription",
                    "storePaymentMethod" => true,
                    "shopperInteraction" => "Ecommerce",
                    'shopperReference' => $customer->client_id,
                ];

                $country = $parameters['address_country'] ?? $customer->country;
                $stateOrProvince = $parameters['address_state'] ?? $customer->state;
                if ($country && $stateOrProvince) {
                    $adyenData['billingAddress'] = [
                        'country' => $country,
                        'stateOrProvince' => $stateOrProvince,
                        'houseNumberOrName' => $customer->address2 ?? '',
                        'street' => $parameters['address_address1'] ?? $customer->address1 ?? '',
                        'city' => $parameters['address_city'] ?? $customer->city ?? '',
                        'postalCode' => $parameters['address_postal_code'] ?? $customer->postal_code ?? '',
                    ];
                }

                $response = $this->adyen->verifyBankAccount($adyenData);
                if (!isset($response['additionalData']['bankVerificationResult'])) {
                    throw new PaymentSourceException('Verification not enabled for your account. Ask support to enable gverify for your store.');
                }
              
                if ($response['additionalData']['bankVerificationResult'] === 'Passed') {
                    return $this->makeAchBankAccount($customer, $account, $parameters, $response['additionalData']['recurring.recurringDetailReference'], $response['additionalData']['recurring.shopperReference']);
                }
              
                throw new PaymentSourceException('Invalid bank account: ' . $response['additionalData']['bankVerificationResultRaw']);
            } catch(IntegrationApiException $e) {
                throw new PaymentSourceException('Could not verify Bank Account or Routing Number.');
            }
        }

        if ('card' == $paymentMethod) {
            // The payment has already been performed and is successful. Here we obtain the result of that payment
            // from our database and use that to reconcile.
            /** @var AdyenPaymentResult $paymentResult */
            $paymentResult = AdyenPaymentResult::where('reference', $parameters['reference'])->one();
            $data = PaymentFlowReconcileData::fromAdyenResult($paymentResult);

            if (!$data->cardCustomerGateway) {
                $data->cardCustomerGateway = $parameters['shopperReference'] ?? throw new AdyenCardException("Missing 'shopperReference' parameter");
            }

            if (!$data->cardGateway) {
                if ($data->status === FlywirePaymentStatus::Failed->toString() && !empty($data->failureReason)) {
                    throw new PaymentSourceException($data->failureReason);
                }
                $data->cardGateway = $this->getRecurringDataDeprecated($parameters['reference'], $data->cardCustomerGateway, $customer) ?? throw new PaymentSourceException('Missing recurring detail reference');
            }

            return $data->toSourceValueObject($customer, $account, true, $parameters['receipt_email'] ?? null);
        }

        throw new PaymentSourceException('Unsupported payment method');
    }

    //@deprecated in Adyen v68
    private function getRecurringDataDeprecated(string $gatewayReference, string $shopperReference, Customer $customer): ?string
    {
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $customer->tenant()->country);
        try {
            $recurringDetails = $this->adyen->getRecurringDetails($adyenMerchantAccount, $shopperReference);
        } catch (IntegrationApiException) {
            return null;
        }
        if (isset($recurringDetails['details'])) {
            foreach ($recurringDetails['details'] as $recurringDetail) {
                $reference = $recurringDetail['RecurringDetail']['name'];
                if ($reference == $gatewayReference) {
                    return $recurringDetail['RecurringDetail']['recurringDetailReference'];
                }
            }
        }

        return null;
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        if ($source instanceof BankAccount) {
            $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $source->tenant()->country);
            $merchantAccount = $source->getMerchantAccount();
            $adyenAccount = AdyenAccount::one();
            $paymentParams = [
                'merchantAccount' => $adyenMerchantAccount,
                'store' => $merchantAccount->gateway_id,
                'amount' => [
                    'currency' => strtoupper($amount->currency),
                    'value' => (string) $amount->amount,
                ],
                'reference' => RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC),
                'shopperStatement' => $adyenAccount->getStatementDescriptor(),
                'paymentMethod' => [
                    'type' => 'scheme',
                    'storedPaymentMethodId' => $source->gateway_id,
                ],
                'platformChargebackLogic' => $this->makeChargebackLogic($merchantAccount),
                'shopperReference' => $source->gateway_customer,
                'shopperInteraction' => 'ContAuth',
                'recurringProcessingModel' => 'UnscheduledCardOnFile',
            ];

            return $this->performChargeAchModel($adyenAccount, $merchantAccount, $source, $amount, $description, $parameters, $paymentParams);
        }

        if ($source instanceof Card) {
            return $this->performChargeCardModel($source, $amount, $documents, $description);
        }

        throw new ChargeException('Unsupported payment method');
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        if ($source instanceof Card) {
            try {
                $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $account->tenant()->country);
                $this->adyen->disableRecurringDetails($adyenMerchantAccount, (string) $source->gateway_customer, (string) $source->gateway_id);
            } catch (IntegrationApiException) {
                throw new PaymentSourceException('Unable to delete payment source');
            }
        }

        // Nothing to do for ACH
    }

    //
    // RefundInterface
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        $reference = RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC);
        // we send cancel request only and wait for webhook
        // because in Adyen word we should send either, but
        // we never know why, because Adyen always returns 201 response
        // and we can't verify if the payment was captured
        // prior sending request, because - Adyen lacks the API endpoint
        try {
            $pspReference = $this->cancel($merchantAccount, $reference, $chargeId);
        } catch (IntegrationApiException $e) {
            throw new RefundException($e->getMessage());
        }

        return new RefundValueObject(
            amount: $amount,
            gateway: AdyenGateway::ID,
            gatewayId: $pspReference,
            status: RefundValueObject::PENDING,
        );
    }

    private function getAdyenMerchantAccount(MerchantAccount $merchantAccount): string
    {
        return AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $merchantAccount->tenant()->country);
    }

    //
    // Dispute Interface
    //

    public function defendDispute(string $adyenMerchantAccount, string $disputePspReference, string $reasonCode): bool
    {
        try {
            $result = $this->adyen->defendDispute($adyenMerchantAccount, $disputePspReference, $reasonCode);
        } catch (IntegrationApiException $e) {
            throw new DisputeException($e->getMessage());
        }

        if (!($result['disputeServiceResult']['success'] ?? false)) {
            throw new DisputeException('Failed to defend dispute: '.($result['disputeServiceResult']['errorMessage'] ?? 'Unknown error'));
        }

        return true;
    }

    public function getDefenceCodes(string $adyenMerchantAccount, string $disputePspReference): array
    {
        try {
            $result = $this->adyen->getDefenceCodes($adyenMerchantAccount, $disputePspReference);
        } catch (IntegrationApiException $e) {
            throw new DisputeException($e->getMessage());
        }

        return $result['defenseReasons'] ?? [];
    }

    public function supplyDefenseDocuments(string $adyenMerchantAccount, string $disputePspReference, array $defenceDocuments): void
    {
        try {
            $result = $this->adyen->supplyDefenseDocuments($adyenMerchantAccount, $disputePspReference, $defenceDocuments);
        } catch (IntegrationApiException $e) {
            throw new DisputeException($e->getMessage());
        }

        if (!($result['disputeServiceResult']['success'] ?? false)) {
            throw new DisputeException('Failed to defend dispute: '.($result['disputeServiceResult']['errorMessage'] ?? 'Unknown error'));
        }
    }

    public function deleteDefenceDocument(string $adyenMerchantAccount, string $defenseDocumentType, string $disputePspReference): bool
    {
        try {
            $result = $this->adyen->deleteDefenceDocument($adyenMerchantAccount, $defenseDocumentType, $disputePspReference);
        } catch (IntegrationApiException $e) {
            throw new DisputeException($e->getMessage());
        }

        return $result['disputeServiceResult']['success'] ?? false;
    }

    public function acceptDispute(string $adyenMerchantAccount, string $disputePspReference): bool
    {
        try {
            $result = $this->adyen->acceptDispute($adyenMerchantAccount, $disputePspReference);
        } catch (IntegrationApiException $e) {
            throw new DisputeException($e->getMessage());
        }

        return $result['disputeServiceResult']['success'] ?? false;
    }

    //
    // Helpers
    //

    /**
     * @throws ChargeException
     */
    public function buildChargeCardReference(Customer $customer, MerchantAccount $account, Money $amount, string $description, array $parameters): ChargeValueObject
    {
        // The payment has already been performed and is successful. Here we obtain the result of that payment
        // from our database and use that to reconcile.
        $paymentResult = AdyenPaymentResult::where('reference', $parameters['reference'])->one();
        $data = PaymentFlowReconcileData::fromAdyenResult($paymentResult, $amount);

        return $this->paymentFlowReconcile->buildChargeReference($customer, $account, $amount, $data, PaymentMethod::CREDIT_CARD, $parameters['receipt_email'] ?? null, $description);
    }

    /**
     * @throws ChargeException
     */
    private function performChargeAchForm(Customer $customer, MerchantAccount $account, Money $amount, string $description, array $parameters): ChargeValueObject
    {
        try {
            $bankAccountValueObject = $this->makeAchBankAccount($customer, $account, $parameters);
            // Validate the bank account details
            $validator = new BankAccountValidator();
            $validator->checkRoutingNumber($bankAccountValueObject);
            $validator->checkCurrency($bankAccountValueObject);
            $validator->checkAccountType($bankAccountValueObject);
            $validator->checkAccountHolderType($bankAccountValueObject);

            /** @var BankAccount $bankAccountModel */
            $bankAccountModel = $this->sourceReconciler->reconcile($bankAccountValueObject);
        } catch (ReconciliationException|InvalidBankAccountException $e) {
            throw new ChargeException($e->getMessage());
        }
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $bankAccountModel->tenant()->country);
        $merchantAccount = $bankAccountModel->getMerchantAccount();
        $adyenAccount = AdyenAccount::one();

        $paymentParams = [
            'merchantAccount' => $adyenMerchantAccount,
            'store' => $merchantAccount->gateway_id,
            'amount' => [
                'currency' => strtoupper($amount->currency),
                'value' => (string) $amount->amount,
            ],
            'reference' => RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC),
            'shopperStatement' => $adyenAccount->getStatementDescriptor(),
            'paymentMethod' => [
                'type' => 'ach',
                'bankAccountNumber' => $parameters['account_number'],
                'bankAccountType' => $bankAccountModel->type ?: 'checking',
                'bankLocationId' => $bankAccountModel->routing_number,
                'ownerName' => $bankAccountModel->account_holder_name,
            ],
            'platformChargebackLogic' => $this->makeChargebackLogic($merchantAccount),
        ];

        return $this->performChargeAchModel($adyenAccount, $merchantAccount, $bankAccountModel, $amount, $description, $parameters, $paymentParams);
    }

    /**
     * @throws ChargeException
     */
    private function performChargeFlywirePaymentForm(Customer $customer, MerchantAccount $account, Money $amount, string $description, array $parameters): ChargeValueObject
    {
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $account->tenant()->country);
        $adyenAccount = AdyenAccount::one();

        $paymentMethod = json_decode($parameters['payment_card'], true);

        $paymentParams = [
            'merchantAccount' => $adyenMerchantAccount,
            'store' => $account->gateway_id,
            'amount' => [
                'currency' => strtoupper($amount->currency),
                'value' => (string) $amount->amount,
            ],
            'reference' => RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC),
            'shopperStatement' => $adyenAccount->getStatementDescriptor(),
            'paymentMethod' => $paymentMethod,
            'platformChargebackLogic' => $this->makeChargebackLogic($account),
        ];

        return $this->performChargeFlywireFormModel($adyenAccount, $account, $customer, $amount, $description, $parameters, $paymentParams);
    }

    /**
     * @throws ChargeException
     */
    private function performChargeFlywireFormModel(AdyenAccount $adyenAccount, MerchantAccount $merchantAccount, Customer $customer, Money $amount, string $description, array $parameters, array $paymentParams): ChargeValueObject
    {
        // Calculate any custom pricing
        $pricingConfiguration = $adyenAccount->pricing_configuration;
        if ($pricingConfiguration) {
            $fee = AdyenPricingEngine::priceCreditCardTransaction($pricingConfiguration, $amount);
            if ($fee) {
                $paymentParams['splits'] = $this->makeSplits($merchantAccount, $amount, $fee);
            }
        }
        // Create the payment
        try {
            $result = $this->adyen->createPayment($paymentParams);
            $paymentSource = null;

            try {
                $adyenResult = new AdyenPaymentResult();
                $adyenResult->result = (string) json_encode($result);
                $adyenResultData = PaymentFlowReconcileData::fromAdyenResult($adyenResult);
                $card = $adyenResultData->toSourceValueObject($customer, $merchantAccount, false, null);

                $paymentSource = $this->sourceReconciler->reconcile($card);
            } catch (Throwable) {
            }

        } catch (IntegrationApiException $e) {
            throw new ChargeException('Unable to create Flywire Credit Card payment: '.$e->getMessage());
        }

        // Build the response
        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result['pspReference'],
            method: PaymentMethod::CREDIT_CARD,
            status: self::AUTHORIZED === $result['resultCode'] ? ChargeValueObject::SUCCEEDED : ChargeValueObject::FAILED,
            merchantAccount: $merchantAccount,
            source: $paymentSource,
            description: $description,
            failureReason: $result['refusalReason'] ?? null,
        );
    }

    /**
     * @throws ChargeException
     */
    private function performChargeAchModel(AdyenAccount $adyenAccount, MerchantAccount $merchantAccount, BankAccount $bankAccount, Money $amount, string $description, array $parameters, array $paymentParams): ChargeValueObject
    {
        // Determine billing address
        $customer = $bankAccount->customer;
        if (isset($parameters['address_address1'])) {
            $streetAddress = trim($parameters['address_address1']);
            $city = $parameters['address_city'] ?? null;
            $stateOrProvince = $parameters['address_state'] ?? null;
            $postalCode = $parameters['address_postal_code'] ?? null;
            $country = $parameters['address_country'] ?? null;
        } else {
            $streetAddress = trim($customer->address1.' '.$customer->address2);
            $city = $customer->city;
            $stateOrProvince = $customer->state;
            $postalCode = $customer->postal_code;
            $country = $customer->country;
        }

        $streetAddressParts = explode(' ', $streetAddress);
        if ($streetAddress && count($streetAddressParts) >= 2) {
            $houseNumberOrName = $streetAddressParts[0];
            unset($streetAddressParts[0]);
            $street = implode(' ', $streetAddressParts);
            $paymentParams['billingAddress'] = [
                'houseNumberOrName' => $houseNumberOrName,
                'street' => $street,
                'city' => $city,
                'stateOrProvince' => $stateOrProvince,
                'postalCode' => $postalCode,
                'country' => $country,
            ];
        }

        // Calculate any custom pricing
        $pricingConfiguration = $adyenAccount->pricing_configuration;
        if ($pricingConfiguration) {
            $fee = AdyenPricingEngine::priceAchTransaction($pricingConfiguration, $amount);
            if ($fee) {
                $paymentParams['splits'] = $this->makeSplits($merchantAccount, $amount, $fee);
            }
        }

        // Create the payment
        try {
            $result = $this->adyen->createPayment($paymentParams);
        } catch (IntegrationApiException $e) {
            throw new ChargeException('Unable to create ACH payment: '.$e->getMessage());
        }

        // Build the response
        return new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result['pspReference'],
            method: PaymentMethod::ACH,
            status: self::AUTHORIZED != $result['resultCode'] ? ChargeValueObject::FAILED : ChargeValueObject::PENDING,
            merchantAccount: $bankAccount->getMerchantAccount(),
            source: $bankAccount,
            description: $description,
            failureReason: $result['refusalReason'] ?? null,
        );
    }

    /**
     * @throws ChargeException
     */
    private function performChargeCardModel(Card $card, Money $amount, array $documents, string $description): ChargeValueObject
    {
        // Build the payment request
        $merchantAccount = $card->getMerchantAccount();
        $customer = $card->customer;
        $adyenMerchantAccount = AdyenConfiguration::getMerchantAccount($this->adyenLiveMode, (string) $card->tenant()->country);
        $adyenAccount = AdyenAccount::one();
        $paymentParams = [
            'shopperStatement' => $adyenAccount->getStatementDescriptor(),
            'merchantAccount' => $adyenMerchantAccount,
            'store' => $merchantAccount->gateway_id,
            'amount' => [
                'currency' => strtoupper($amount->currency),
                'value' => (string) $amount->amount,
            ],
            'reference' => RandomString::generate(32, RandomString::CHAR_LOWER.RandomString::CHAR_NUMERIC),
            'paymentMethod' => [
                'type' => 'scheme',
                'storedPaymentMethodId' => $card->gateway_id,
            ],
            'platformChargebackLogic' => $this->makeChargebackLogic($merchantAccount),
            'shopperReference' => $card->gateway_customer,
            'shopperInteraction' => 'ContAuth',
            'recurringProcessingModel' => 'UnscheduledCardOnFile',
        ];

        // Add Level 2/3 data
        $paymentParams['additionalData'] = $this->makeLevel3($documents, $customer, $amount);

        // Calculate any custom pricing
        $pricingConfiguration = $adyenAccount->pricing_configuration;
        if ($pricingConfiguration) {
            $company = $card->tenant();
            $cardCountry = $card->issuing_country ?: (string) $company->country;
            $isAmex = 'amex' == $card->brand;
            $fee = AdyenPricingEngine::priceCardTransaction($pricingConfiguration, $company, $amount, $cardCountry, $isAmex);
            if ($fee) {
                $paymentParams['splits'] = $this->makeSplits($merchantAccount, $amount, $fee);
            }
        }

        // Create the payment
        try {
            $result = $this->adyen->createPayment($paymentParams);
        } catch (IntegrationApiException) {
            throw new ChargeException('Unable to create stored card payment.');
        }

        $status = self::AUTHORIZED != $result['resultCode'] ? ChargeValueObject::FAILED : ChargeValueObject::SUCCEEDED;

        $chargeObject = new ChargeValueObject(
            customer: $customer,
            amount: $amount,
            gateway: self::ID,
            gatewayId: $result['pspReference'],
            method: PaymentMethod::CREDIT_CARD,
            status: $status,
            merchantAccount: $card->getMerchantAccount(),
            source: $card,
            description: $description,
            failureReason: $result['refusalReason'] ?? null,
        );

        // Check result code
        if (ChargeValueObject::FAILED === $status) {
            throw new ChargeException('We were unable to process your payment.', $chargeObject);
        }

        // Build the response
        return $chargeObject;
    }

    /**
     * Makes the Level 3 parameters to be added to a create payment request.
     */
    public function makeLevel3(array $documents, Customer $customer, Money $amount): array
    {
        $level3 = GatewayHelper::makeLevel3($documents, $customer, $amount);

        // Convert country to alpha 3 code
        $country = (new Countries())->get($level3->shipTo['country'])['alpha3Code'] ?? 'USA';

        $result = [
            'enhancedSchemeData.customerReference' => trim(substr($level3->poNumber, 0, 25)),
            'enhancedSchemeData.destinationCountryCode' => $country,
            'enhancedSchemeData.destinationPostalCode' => substr((string) $level3->shipTo['postal_code'], 0, 10),
            'enhancedSchemeData.destinationStateProvinceCode' => substr((string) $level3->shipTo['state'], 0, 3),
            'enhancedSchemeData.dutyAmount' => '0',
            'enhancedSchemeData.freightAmount' => (string) $level3->shipping->amount,
            'enhancedSchemeData.orderDate' => $level3->orderDate->format('dmy'),
            'enhancedSchemeData.shipFromPostalCode' => substr($level3->merchantPostalCode, 0, 10),
            'enhancedSchemeData.totalTaxAmount' => (string) $level3->salesTax->amount,
        ];

        $lineNumber = 1;
        foreach ($level3->lineItems as $lineItem) {
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.commodityCode'] = substr($lineItem->commodityCode, 0, 12);
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.description'] = trim(substr($lineItem->description, 0, 26));
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.discountAmount'] = (string) $lineItem->discount->amount;
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.productCode'] = substr($lineItem->productCode, 0, 12);
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.quantity'] = (string) $lineItem->quantity;
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.totalAmount'] = (string) $lineItem->total->amount;
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.unitOfMeasure'] = substr($lineItem->unitOfMeasure, 0, 3);
            $result['enhancedSchemeData.itemDetailLine'.$lineNumber.'.unitPrice'] = (string) $lineItem->unitCost->amount;
            ++$lineNumber;
        }

        return $result;
    }

    /**
     * Makes the chargeback logic to be added to a create payment request.
     *
     * @throws ChargeException
     */
    public function makeChargebackLogic(MerchantAccount $merchantAccount): array
    {
        $balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '';
        if (!$balanceAccountId) {
            throw new ChargeException('Missing balance account ID');
        }

        // Ensure chargebacks are deducted from the sub-merchant
        // and that the cost is deducted from our liable account.
        return [
            'behavior' => 'deductFromOneBalanceAccount',
            'targetAccount' => $balanceAccountId,
            // Cost allocation account is not included. Per Adyen documentation:
            // By default, chargeback fees are booked to your liable balance account.
        ];
    }

    /**
     * Makes the splits array to be added to a create payment request
     * when there is a custom calculated fee.
     *
     * @throws ChargeException
     */
    public function makeSplits(MerchantAccount $merchantAccount, Money $amount, Money $fee): array
    {
        $balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '';
        if (!$balanceAccountId) {
            throw new ChargeException('Missing balance account ID');
        }

        $sellerSplit = $amount->subtract($fee);

        return [
            // Seller split
            [
                'amount' => [
                    'value' => $sellerSplit->amount,
                ],
                'type' => 'BalanceAccount',
                'account' => $balanceAccountId,
                'reference' => $merchantAccount->tenant_id.'-'.RandomString::generate(16),
                'description' => 'Seller split',
            ],
            // Platform commission
            [
                'amount' => [
                    'value' => $fee->amount,
                ],
                'type' => 'Commission',
                'reference' => $merchantAccount->tenant_id.'-'.RandomString::generate(16),
                'description' => 'Variable Fee',
            ],
            // Payment fee split is not included. Per Adyen documentation:
            // If you do not include booking instructions for any of the transaction
            // fee types in your payment request, Adyen automatically updates the request
            // to include the PaymentFee split type, booking all transaction fees to your
            // platform's liable balance account.
        ];
    }

    /**
     * @throws RefundException
     */
    public function credit(string $store, MerchantAccount $merchantAccount, string $reference, string $chargeId, Money $amount): string
    {
        $balanceAccountId = $merchantAccount->toGatewayConfiguration()->credentials->balance_account ?? '';
        if (!$balanceAccountId) {
            throw new RefundException('Missing balance account ID');
        }

        $adyenMerchantAccount = $this->getAdyenMerchantAccount($merchantAccount);
        $parameters = [
            'amount' => [
                'currency' => strtoupper($amount->currency),
                'value' => $amount->amount,
            ],
            'reference' => $reference,
            'merchantAccount' => $adyenMerchantAccount,
            'store' => $store,
            'splits' => [
                // The full refund amount comes from the merchant's balance account
                [
                    'amount' => [
                        'value' => $amount->amount,
                    ],
                    'type' => 'BalanceAccount',
                    'account' => $balanceAccountId,
                    'reference' => $merchantAccount->tenant_id.'-'.RandomString::generate(16),
                    'description' => 'Seller split',
                ],
                // Payment fee split is not included. Per Adyen documentation:
                // If you do not include booking instructions for any of the transaction
                // fee types in your refund request, Adyen automatically updates the request
                // to include the PaymentFee split type, booking all transaction fees to your
                // platform's liable balance account.
            ],
        ];

        try {
            $result = $this->adyen->refund($chargeId, $parameters);
        } catch (IntegrationApiException) {
            throw new RefundException('We were unable to process your refund.');
        }

        return $result['pspReference'];
    }

    /**
     * @throws IntegrationApiException
     */
    private function cancel(MerchantAccount $merchantAccount, string $reference, string $chargeId): string
    {
        $adyenMerchantAccount = $this->getAdyenMerchantAccount($merchantAccount);

        $parameters = [
            'reference' => $reference,
            'merchantAccount' => $adyenMerchantAccount,
        ];

        $result = $this->adyen->cancel($chargeId, $parameters);

        return $result['pspReference'];
    }
    /**
     * @throws IntegrationApiException
     */
    public function capture(MerchantAccount $merchantAccount, string $reference, string $chargeId, Money $amount, array $lineItems): string
    {
        $adyenMerchantAccount = $this->getAdyenMerchantAccount($merchantAccount);

        $parameters = [
            'reference' => $reference,
            'merchantAccount' => $adyenMerchantAccount,
            'amount' => [
                'currency' => strtoupper($amount->currency),
                'value' => $amount->amount,
            ],
            'lineItems' => $lineItems,
        ];

        $result = $this->adyen->capture($chargeId, $parameters);

        return $result['pspReference'];
    }

    public function getTransactionStatus(MerchantAccount $merchantAccount, Charge $charge): array
    {
        /** @var MerchantAccountTransaction[] $transactions */
        $transactions = MerchantAccountTransaction::where('source_id', $charge->id)
            ->where('source_type', ObjectType::fromModel($charge)->value)
            ->all();
        $status = Charge::PENDING;

        foreach ($transactions as $transaction) {
            if (MerchantAccountTransactionType::Dispute == $transaction->type) {
                return [Charge::FAILED, $transaction->description];
            }
            //we give 24 extra hours to settle down the report
            //not to change the status twice
            if (CarbonImmutable::createFromTimestamp($transaction->created_at)->diffInDays() > 0) {
                $status = Charge::SUCCEEDED;
            }
        }

        return [$status, null];
    }

    private function makeAchBankAccount(Customer $customer, MerchantAccount $account, array $parameters, ?string $gatewayId = null, ?string $customerId = null): BankAccountValueObject
    {
        $routingNumber = $this->routingNumberLookup->lookup($parameters['routing_number'] ?: '');

        return new BankAccountValueObject(
            customer: $customer,
            gateway: $account->gateway,
            gatewayId: $gatewayId,
            gatewayCustomer: $customerId,
            merchantAccount: $account,
            chargeable: $gatewayId && $customerId,
            receiptEmail: $parameters['receipt_email'] ?? null,
            bankName: $routingNumber?->bank_name ?: 'Unknown',
            routingNumber: $parameters['routing_number'] ?? null,
            last4: substr($parameters['account_number'] ?? '', -4, 4) ?: '0000',
            currency: 'usd',
            country: 'US',
            accountHolderName: $parameters['account_holder_name'] ?? null,
            accountHolderType: $parameters['account_holder_type'] ?? null,
            type: $parameters['type'] ?? null,
            verified: true,
        );
    }
}
