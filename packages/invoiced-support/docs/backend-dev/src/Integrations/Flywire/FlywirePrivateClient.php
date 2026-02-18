<?php

namespace App\Integrations\Flywire;

use App\Core\I18n\ValueObjects\Money;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Flywire\Traits\FlywireTrait;
use App\PaymentProcessing\Models\MerchantAccount;
use Generator;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FlywirePrivateClient extends FlywireClient
{
    use FlywireTrait;
    private const int PER_PAGE = 100;

    private const array REFUND_ERROR_MESSAGES = [
        'refund_bundle_creation_error_already_refund' => 'There already is a refund in process for this payment. Please wait until the refund is completed and try again.',
        'refund_bundle_creation_error_minimum_threshold' => 'The refund amount is not within the minimum refund amount settings for the recipient.',
        'refund_bundle_creation_error_amount_over_remaining_balance' => 'The refund amount is higher than the remaining payment balance.',
        'refund_bundle_creation_error_multiple' => 'You are trying to create multiple refunds for the same payment.',
        'refund_bundle_creation_error_status' => 'Refund creation failed. The payment must be in \'delivered\' status.',
    ];

    public function __construct(
        protected string $flywireClientId,
        protected string $flywireClientSecret,
        protected string $flywirePrivateApiUrl,
        protected string $flywireCheckoutUrl,
        protected HttpClientInterface $httpClient,
        protected GatewayLogger $gatewayLogger,
        protected UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct(
            $flywireClientId,
            $flywireClientSecret,
            $flywirePrivateApiUrl,
            $flywireCheckoutUrl,
            $httpClient,
            $gatewayLogger,
            $urlGenerator
        );
    }

    /**
     * Gets a list of Disbursements.
     *
     * @throws IntegrationApiException
     */
    public function getDisbursements(array $query): array
    {
        return $this->makeApiRequest('POST', '/v3/disbursements/search', $query);
    }

    /**
     * Gets a list of Payments.
     *
     * @throws IntegrationApiException
     */
    public function getPayments(array $query): array
    {
        return $this->makeApiRequest('POST', '/v3/payments/search', $query);
    }

    /**
     * @throws IntegrationApiException
     *
     * @return Generator<array>
     */
    public function getDisbursementPayouts(string $reference): Generator
    {
        $params['per_page'] = self::PER_PAGE;
        $params['page'] = 1;

        do {
            $result = $this->makeApiRequest('GET', "/v3/disbursements/$reference/payouts", $params);
            ++$params['page'];
            yield from $result['payouts'];
        } while (count($result['payouts']) >= $params['per_page']);
    }

    /**
     * Gets a list of Refunds for a Disbursement.
     *
     * @throws IntegrationApiException
     *
     * @return Generator<array>
     */
    public function getDisbursementRefunds(string $reference, string $portalCode): Generator
    {
        $params['per_page'] = self::PER_PAGE;
        $params['page'] = 1;

        do {
            $result = $this->makeApiRequest('GET', "/v3/recipients/$portalCode/disbursements/$reference/refunds", $params);
            ++$params['page'];
            yield from $result['refunds'];
        } while (count($result['refunds']) >= $params['per_page']);
    }

    /**
     * Gets a list of Refund Bundles.
     *
     * @throws IntegrationApiException
     */
    public function getRefundBundles(array $query): array
    {
        return $this->makeApiRequest('POST', '/v3/refunds/bundles/search', $query);
    }

    public function getRefundBundle(string $refundBundleId, string $portalCode): array
    {
        return $this->makeApiRequest('GET', "/v3/recipients/$portalCode/bundles/$refundBundleId");
    }

    /**
     * Retrieves a Payment.
     *
     * @throws IntegrationApiException
     */
    public function getPayment(string $paymentId): array
    {
        return $this->makeApiRequest('GET', "/v3/payments/$paymentId");
    }

    /**
     * Gets a list of Refunds.
     *
     * @throws IntegrationApiException
     */
    public function getRefunds(array $query): array
    {
        return $this->makeApiRequest('POST', '/v3/refunds/search', $query);
    }

    /**
     * Retrieves a Refund.
     *
     * @throws IntegrationApiException
     */
    public function getRefund(string $refundId, string $recipientId): array
    {
        return $this->makeApiRequest('GET', "/v3/recipients/$recipientId/refunds/$refundId");
    }

    /**
     * Creates a Refund.
     *
     * @throws IntegrationApiException
     */
    public function refund(MerchantAccount $merchantAccount, string $paymentId, Money $amount): array
    {
        try {
            $body = [
                'callback_url' => $this->refundCallbackUrl($merchantAccount),
                'requests' => [
                    [
                        'reference' => $paymentId,
                        'amount' => $amount->amount,
                    ],
                ],
            ];

            return $this->makeApiRequest('POST', '/v3/refunds/bundles', $body);
        } catch (IntegrationApiException $parentException) {
            $e = $parentException->getPrevious();
            if ($e instanceof HttpExceptionInterface) {
                if ($msg = $this->getErrorMessage($e->getResponse())) {
                    foreach (self::REFUND_ERROR_MESSAGES as $key => $value) {
                        if (str_contains($msg, $key)) {
                            throw new IntegrationApiException($value);
                        }
                    }

                    throw new IntegrationApiException($msg);
                }

                // A not found error indicates that the payment is not
                // eligible for a refund. At the minimum, the payment
                // must be in a Delivered status to issue a refund.
                if (404 == $e->getResponse()->getStatusCode()) {
                    throw new IntegrationApiException('We were unable to refund this payment because it is not in a Delivered state. Please try again once your payment has a Delivered status.');
                }
            }

            throw new IntegrationApiException('An unknown error occurred when processing your refund.');
        }
    }

    /**
     * Creates a Payment using Checkout and a tokenized payment source.
     *
     * @throws IntegrationApiException
     */
    public function pay(MerchantAccount $account, string $id, array $data): array
    {
        $data['callback'] = [
            'id' => $id,
            'url' => $this->paymentCallbackUrl($account),
            'version' => '2',
        ];

        $secret = FlywireHelper::getSecret($account);
        $digest = base64_encode(hash_hmac('sha256', (string) json_encode($data), $secret, true));

        try {
            $response = $this->makeRequest('POST', $this->flywireCheckoutUrl, [
                'headers' => [
                    'X-Flywire-Digest' => $digest,
                ],
                'json' => $data,
            ]);

            return $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('Could not process Flywire payment', ['exception' => $e]);

            throw new IntegrationApiException('An unknown error occurred when processing your payment.');
        }
    }
}
