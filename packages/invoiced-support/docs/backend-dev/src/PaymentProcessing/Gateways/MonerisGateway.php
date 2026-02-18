<?php

namespace App\PaymentProcessing\Gateways;

use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\Core\I18n\ValueObjects\Money;
use App\PaymentProcessing\Exceptions\ChargeException;
use App\PaymentProcessing\Exceptions\InvalidBankAccountException;
use App\PaymentProcessing\Exceptions\InvalidGatewayConfigurationException;
use App\PaymentProcessing\Exceptions\PaymentSourceException;
use App\PaymentProcessing\Exceptions\ReconciliationException;
use App\PaymentProcessing\Exceptions\RefundException;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Interfaces\RefundInterface;
use App\PaymentProcessing\Interfaces\TestCredentialsInterface;
use App\PaymentProcessing\Libs\GatewayHelper;
use App\PaymentProcessing\Libs\HttpClientFactory;
use App\PaymentProcessing\Models\BankAccount;
use App\PaymentProcessing\Models\Card;
use App\PaymentProcessing\Models\MerchantAccount;
use App\PaymentProcessing\Models\PaymentSource;
use App\PaymentProcessing\ValueObjects\ChargeValueObject;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use App\PaymentProcessing\ValueObjects\RefundValueObject;
use App\PaymentProcessing\ValueObjects\SourceValueObject;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;

class MonerisGateway extends AbstractLegacyGateway implements RefundInterface, TestCredentialsInterface
{
    const ID = 'moneris';

    private const PRODUCTION_URL_CA = 'https://www3.moneris.com/gateway2/servlet/MpgRequest';
    private const SANDBOX_URL_CA = 'https://esqa.moneris.com/gateway2/servlet/MpgRequest';
    private const PRODUCTION_URL_US = 'https://esplus.moneris.com/gateway_us/servlet/MpgRequest';
    private const SANDBOX_URL_US = 'https://esplusqa.moneris.com/gateway_us/servlet/MpgRequest';

    private const MASK_REGEXES = [
        '/\<api_token\>(.*)\<\/api_token\>/',
        '/\<pan\>(.*)\<\/pan\>/',
        '/\<cvd_value\>(.*)\<\/cvd_value\>/',
        '/\<account_num\>(.*)\<\/account_num\>/',
    ];

    public function validateConfiguration(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        if (!isset($gatewayConfiguration->credentials->processing_country)) {
            throw new InvalidGatewayConfigurationException('Missing Moneris processing country!');
        }

        if (!isset($gatewayConfiguration->credentials->store_id)) {
            throw new InvalidGatewayConfigurationException('Missing Moneris store ID!');
        }

        if (!isset($gatewayConfiguration->credentials->api_token)) {
            throw new InvalidGatewayConfigurationException('Missing Moneris API token!');
        }
    }

    //
    // One-Time Charges
    //

    public function charge(Customer $customer, MerchantAccount $account, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            try {
                $bankAccountValueObject = GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, false);
                /** @var BankAccount $bankAccountModel */
                $bankAccountModel = $this->sourceReconciler->reconcile($bankAccountValueObject);
            } catch (ReconciliationException|InvalidBankAccountException $e) {
                throw new ChargeException($e->getMessage());
            }

            return $this->chargeBankAccount($bankAccountModel, $account, $amount, $documents, $description);
        }

        // Other payment types fall back to the payment server
        return parent::charge($customer, $account, $amount, $parameters, $description, $documents);
    }

    //
    // Payment Sources
    //

    public function vaultSource(Customer $customer, MerchantAccount $account, array $parameters): SourceValueObject
    {
        // Handle ACH payment information
        $paymentMethod = $parameters['payment_method'] ?? '';
        if ('ach' == $paymentMethod) {
            try {
                return GatewayHelper::makeAchBankAccount($this->routingNumberLookup, $customer, $account, $parameters, true);
            } catch (InvalidBankAccountException $e) {
                throw new PaymentSourceException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // Other payment types fall back to the payment server
        return parent::vaultSource($customer, $account, $parameters);
    }

    public function chargeSource(PaymentSource $source, Money $amount, array $parameters, string $description, array $documents = []): ChargeValueObject
    {
        // Charge a bank account vaulted in our database instead of on the gateway
        $account = $source->getMerchantAccount();
        if ($source instanceof BankAccount && $source->account_number) {
            return $this->chargeBankAccount($source, $account, $amount, $documents, $description);
        }

        if (!$source instanceof Card) {
            throw new ChargeException('Unsupported payment source type: '.$source->object);
        }

        $gatewayConfiguration = $account->toGatewayConfiguration();
        $request = $this->buildRequest($gatewayConfiguration);

        $country = $gatewayConfiguration->credentials->processing_country;
        if ('US' == $country) {
            $purchase = $request->addChild('us_res_purchase_cc');
        } else {
            $purchase = $request->addChild('res_purchase_cc');
        }

        $purchase->addChild('order_id', uniqid()); // this must be unique with every request
        $purchase->addChild('data_key', $source->gateway_id);
        $purchase->addChild('amount', $this->formatNumber($amount));
        $purchase->addChild('crypt_type', '7'); // SSL enabled merchant

        if (count($documents) > 0) {
            $purchase->addChild('cust_id', (string) $documents[0]->id);
        }

        try {
            $response = $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with the Moneris gateway.');
        }

        $result = $this->parseResponse($response);

        return $this->parseChargeResponse($result, $source, $amount, $description);
    }

    public function deleteSource(MerchantAccount $account, PaymentSource $source): void
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $request = $this->buildRequest($gatewayConfiguration);

        $country = $gatewayConfiguration->credentials->processing_country;
        if ('US' == $country) {
            $delete = $request->addChild('us_res_delete');
        } else {
            $delete = $request->addChild('res_delete');
        }

        $delete->addChild('data_key', $source->gateway_id);

        try {
            $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException) {
            throw new PaymentSourceException('An unknown error has occurred when communicating with the Moneris gateway.');
        }
    }

    //
    // Refunds
    //

    public function refund(MerchantAccount $merchantAccount, string $chargeId, Money $amount): RefundValueObject
    {
        try {
            $ids = $this->getChargeIds($chargeId);

            $gatewayConfiguration = $merchantAccount->toGatewayConfiguration();
            $request = $this->buildRequest($gatewayConfiguration);
            $refund = $request->addChild('refund');
            $refund->addChild('order_id', $ids['order_id']);
            $refund->addChild('amount', $this->formatNumber($amount));
            $refund->addChild('txn_number', $ids['txn_number']);
            $refund->addChild('crypt_type', '7');
            $response = $this->performRequest($gatewayConfiguration, $request);
            $result = $this->parseResponse($response);
            $complete = (string) $result->receipt->Complete;
            if ('true' != $complete) {
                throw new RefundException((string) $result->receipt->Message);
            }
        } catch (GuzzleException) {
            throw new RefundException('An unknown error has occurred when communicating with the Moneris gateway.');
        }

        return new RefundValueObject(
            amount: $amount,
            gateway: self::ID,
            gatewayId: (string) $result->receipt->ReferenceNum,
            status: RefundValueObject::SUCCEEDED,
        );
    }

    //
    // Test Credentials
    //

    public function testCredentials(PaymentGatewayConfiguration $gatewayConfiguration): void
    {
        // we test the credentials by running a void that is known to fail
        // based on the failure response we can determine if the credentials are valid

        $request = $this->buildRequest($gatewayConfiguration);

        $country = $gatewayConfiguration->credentials->processing_country;
        if ('US' == $country) {
            $void = $request->addChild('purchasecorrection');
        } else {
            $void = $request->addChild('purchasecorrection');
        }

        $void->addChild('order_id', uniqid());
        $void->addChild('txn_number', '837155-0_25');
        $void->addChild('crypt_type', '7');

        try {
            $response = $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException) {
            throw new TestGatewayCredentialsException('An unknown error has occurred when communicating with the Moneris gateway.');
        }

        $this->parseResponse($response);
    }

    //
    // Helpers
    //

    private function getClient(PaymentGatewayConfiguration $gatewayConfiguration): Client
    {
        $country = $gatewayConfiguration->credentials->processing_country;

        if ('US' == $country) {
            $url = self::PRODUCTION_URL_US;
            if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
                $url = self::SANDBOX_URL_US;
            }
        } else {
            $url = self::PRODUCTION_URL_CA;
            if (isset($gatewayConfiguration->credentials->test_mode) && $gatewayConfiguration->credentials->test_mode) {
                $url = self::SANDBOX_URL_CA;
            }
        }

        return HttpClientFactory::make($this->gatewayLogger, [
            'base_uri' => $url,
            'connect_timeout' => 20,
            'read_timeout' => 35,
        ]);
    }

    private function buildRequest(PaymentGatewayConfiguration $gatewayConfiguration): SimpleXMLElement
    {
        $request = new SimpleXMLElement('<request></request>');
        $request->addChild('store_id', $gatewayConfiguration->credentials->store_id);
        $request->addChild('api_token', $gatewayConfiguration->credentials->api_token);

        return $request;
    }

    /**
     * @throws GuzzleException
     */
    private function performRequest(PaymentGatewayConfiguration $gatewayConfiguration, SimpleXMLElement $request): ResponseInterface
    {
        $this->gatewayLogger->logXmlRequest($request, self::MASK_REGEXES);

        return $this->getClient($gatewayConfiguration)->post('', [
            'body' => $request->asXML(),
        ]);
    }

    /**
     * Parses a response from the Moneris gateway.
     */
    private function parseResponse(ResponseInterface $response): SimpleXMLElement
    {
        $result = simplexml_load_string($response->getBody());
        if (!$result) {
            throw new Exception('Received invalid XML');
        }

        return $result;
    }

    private function formatNumber(Money $amount): string
    {
        return number_format($amount->toDecimal(), 2, '.', '');
    }

    /**
     * @return string[]
     */
    private function getChargeIds(string $chargeId): array
    {
        $chargeIds = explode(':', $chargeId);

        return [
            'order_id' => $chargeIds[0] ?? '',
            'txn_number' => $chargeIds[1] ?? '',
        ];
    }

    private function parseChargeResponse(SimpleXMLElement $result, PaymentSource $source, Money $amount, string $description): ChargeValueObject
    {
        $complete = (string) $result->receipt->Complete;
        $responseCode = (string) $result->receipt->ResponseCode;
        if ('true' == $complete && $responseCode >= 0 && $responseCode <= 49) {
            // success
            return $this->buildCharge($result, $source, $amount, ChargeValueObject::SUCCEEDED, $description);
        } elseif ($responseCode >= 50 && $responseCode <= 999) {
            // declined
            throw new ChargeException((string) $result->receipt->Message);
        }

        // incomplete
        throw new ChargeException('An unknown error has occurred');
    }

    /**
     * Builds a charge object from an Moneris transaction response.
     */
    private function buildCharge(SimpleXMLElement $result, PaymentSource $source, Money $amount, string $status, string $description): ChargeValueObject
    {
        // Check if there was a partial authorization
        $total = $amount;
        if (isset($result['redeemedAmount'])) {
            $total = Money::fromDecimal($amount->currency, (float) $result->TransAmount);
        }

        return new ChargeValueObject(
            customer: $source->customer,
            amount: $total,
            gateway: self::ID,
            gatewayId: ((string) $result->receipt->ReceiptId).':'.((string) $result->receipt->TransID),
            method: '',
            status: $status,
            merchantAccount: $source->getMerchantAccount(),
            source: $source,
            description: $description,
            failureReason: (string) $result->receipt->Message,
        );
    }

    /**
     * @param ReceivableDocument[] $documents
     *
     * @throws ChargeException
     */
    private function chargeBankAccount(BankAccount $bankAccount, MerchantAccount $account, Money $amount, array $documents, string $description): ChargeValueObject
    {
        $gatewayConfiguration = $account->toGatewayConfiguration();
        $request = $this->buildRequest($gatewayConfiguration);

        $purchase = $request->addChild('ach_debit');

        $purchase->addChild('order_id', uniqid()); // this must be unique with every request
        $purchase->addChild('amount', $this->formatNumber($amount));
        $purchase->addChild('account_num', $bankAccount->account_number);
        $purchase->addChild('routing_num', $bankAccount->routing_number);
        $purchase->addChild('sec', GatewayHelper::secCodeWeb($gatewayConfiguration));
        $purchase->addChild('account_type', $bankAccount->account_holder_type);
        $purchase->addChild('crypt_type', '7'); // SSL enabled merchant

        if (count($documents) > 0) {
            $purchase->addChild('cust_id', (string) $documents[0]->id);
        }

        // address
        // TODO

        try {
            $response = $this->performRequest($gatewayConfiguration, $request);
        } catch (GuzzleException) {
            throw new ChargeException('An unknown error has occurred when communicating with the Moneris gateway.');
        }

        $result = $this->parseResponse($response);

        return $this->parseChargeResponse($result, $bankAccount, $amount, $description);
    }
}
