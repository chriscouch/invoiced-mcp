<?php

namespace App\Integrations\Opp;

use App\Integrations\Exceptions\IntegrationApiException;
use App\PaymentProcessing\Libs\GatewayLogger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OPPClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const array MASKED_REQUEST_PARAMETERS = [
        'cvc',
        'account_number',
    ];

    public function __construct(
        private readonly string $oppClientUrl,
        private readonly string $oppUiUrl,
        private readonly string $oppUrl,
        private readonly string $oppAccessToken,
        private readonly string $oppKey,
        private readonly HttpClientInterface $httpClient,
        private readonly GatewayLogger $gatewayLogger,
        private readonly string $oppAuthAccessToken,
        private readonly string $oppAuthKey,
    ) {
    }

    public function addCustomer(string $firstName, string $lastName, ?string $email): array
    {
        try {
            $payload = [
                'customer' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'emailAddress' => $email
                ]
            ];
            $payload = $this->appendInvoicedAuthenticationToPayload($payload);

            $response = $this->makeRequest('POST', $this->oppClientUrl.'/webservice/addCustomer', $payload);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not process Opp customer', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when processing your payment.');
        }
    }

    public function confirmPaymentMethod(?string $customerToken, array $payload): array
    {
        try {
            $payload = $this->appendInvoicedAuthenticationToPayload($payload);
            $payload = $this->appendCustomerTokenToPayload($payload, $customerToken);

            $response = $this->makeRequest('POST', $this->oppClientUrl.'/webservice/confirmPaymentMethod', $payload);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not confirm Opp payment method', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when confirming your payment method.');
        }
    }

    public function deletePaymentMethod(?string $customerToken, array $paymentMethodData): array
    {
        try {
            $payload = [
                'paymentMethod' => $paymentMethodData
            ];
            $payload = $this->appendAuthenticationToPayload($payload);
            $payload = $this->appendCustomerTokenToPayload($payload, $customerToken);

            $response = $this->makeRequest('POST', $this->oppUrl.'/webservice/deletePaymentMethod', $payload);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not delete Opp payment method', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when deleting your payment method.');
        }
    }

    public function makePayment(?string $customerToken, ?string $paymentMethodToken, array $data): array
    {
        try {
            $payload = [
                'transaction' => $data
            ];
            $payload = $this->appendAuthenticationToPayload($payload);
            $payload = $this->appendCustomerTokenToPayload($payload, $customerToken);
            $payload = $this->appendPaymentMethodTokenToPayload($payload, $paymentMethodToken);

            $response = $this->makeRequest('POST', $this->oppUrl.'/webservice/payment', $payload);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not process Opp payment', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when processing your payment.');
        }
    }

    public function makeRefund(?string $customerToken, ?string $paymentMethodToken, array $data): array
    {
        try {
            $payload = [
                'transaction' => $data
            ];
            $payload = $this->appendAuthenticationToPayload($payload);
            $payload = $this->appendCustomerTokenToPayload($payload, $customerToken);
            $payload = $this->appendPaymentMethodTokenToPayload($payload, $paymentMethodToken);

            $response = $this->makeRequest('POST', $this->oppUrl.'/webservice/refund', $payload);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not process Opp payment', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when processing your payment.');
        }
    }

    public function voidPayment(?string $customerToken, ?string $paymentMethodToken, array $data): array
    {
        try {
            $payload = [
                'transaction' => $data
            ];
            $payload = $this->appendAuthenticationToPayload($payload);
            $payload = $this->appendCustomerTokenToPayload($payload, $customerToken);
            $payload = $this->appendPaymentMethodTokenToPayload($payload, $paymentMethodToken);

            $response = $this->makeRequest('POST', $this->oppUrl.'/webservice/void', $payload);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not void Opp payment', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when voiding your payment.');
        }
    }

    public function getTransactionStatus(?string $transactionId): array
    {
        try {
            $payload = [
                'transactionId' => $transactionId
            ];
            $payload = $this->appendAuthenticationToPayload($payload);

            $response = $this->makeRequest('POST', $this->oppUrl.'/webservice/transactions/query', $payload);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not get Opp payment', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when retrieving your payment.');
        }
    }

    /**
     * @throws ExceptionInterface
     */
    private function makeRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->gatewayLogger->logSymfonyHttpRequest($method, $url, $options, self::MASKED_REQUEST_PARAMETERS);

        try {
            $headers = [];
            $descriptor = $options['transaction']['descriptor'] ?? null;
            if ($descriptor && is_string($descriptor)) {
               $headers['x-request-id'] = $descriptor;
            }
            $response = $this->httpClient->request($method, $url, [
                'json' => $options,
                'headers' => $headers
            ]);
            $this->gatewayLogger->logSymfonyHttpResponse($response);

            return $response;
        } catch (ExceptionInterface $e) {
            // log the response before rethrowing
            if ($e instanceof HttpExceptionInterface) {
                $response = $e->getResponse();
                $this->gatewayLogger->logSymfonyHttpResponse($response);
            }

            throw $e;
        }
    }

    /**
     * @param array $payload
     * @return array|string[]
     */
    private function appendAuthenticationToPayload(array $payload): array
    {
        $payload += ['authenticationRequest' => [
            'accessToken' => $this->oppAuthAccessToken,
            'key' => $this->oppAuthKey
        ]];
        return $payload;
    }

    private function appendInvoicedAuthenticationToPayload(array $payload): array
    {
        $payload += ['authenticationRequest' => [
            'accessToken' => $this->oppAccessToken,
            'key' => $this->oppKey
        ]];
        return $payload;
    }

    /**
     * @param array $payload
     * @param string $customerToken
     * @return array|string[]
     */
    private function appendCustomerTokenToPayload(array $payload, ?string $customerToken): array
    {
        $payload += ['customer' => [
            'token' => $customerToken
        ]];
        return $payload;
    }

    /**
     * @param array $payload
     * @param string $paymentMethodToken
     * @return array|string[]
     */
    private function appendPaymentMethodTokenToPayload(array $payload, ?string $paymentMethodToken): array
    {
        $payload += ['paymentMethod' => [
            'token' => [
                'value' => $paymentMethodToken
            ]
        ]];
        return $payload;
    }

    public function getOppUiUrl(): string
    {
        return $this->oppUiUrl;
    }
}