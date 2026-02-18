<?php

namespace App\Integrations\Flywire;

use App\Integrations\Exceptions\IntegrationApiException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\PaymentProcessing\Libs\GatewayLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FlywireRefundApproveClient extends FlywireClient
{
    protected const array REFUND_APPROVE_ERROR_MESSAGES = [
        'refund_bundle_creation_error_already_approved' => 'This refund has already been approved.', // todo is this list ok ?
    ];

    public function __construct(
        protected string $flywireRefundApprovalClientId,
        protected string $flywireRefundApprovalClientSecret,
        protected string $flywirePrivateApiUrl,
        protected string $flywireCheckoutUrl,
        protected HttpClientInterface $httpClient,
        protected GatewayLogger $gatewayLogger,
        protected UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct(
            $flywireRefundApprovalClientId,
            $flywireRefundApprovalClientSecret,
            $flywirePrivateApiUrl,
            $flywireCheckoutUrl,
            $httpClient,
            $gatewayLogger,
            $urlGenerator
        );
    }

    /**
     * Creates a Refund.
     * docs: https://api-docs.flywire.com/reference/v3/refunds_bundles#/Refunds%20Bundles/post_v3_refunds_bundles__bundleId__approve
     *
     * @param string $bundleId
     * @return array
     * @throws IntegrationApiException
     * @throws TransportExceptionInterface
     */
    public function approveRefund(string $bundleId): array
    {
        try {
            return $this->makeApiRequest('POST', '/v3/refunds/bundles/' . $bundleId . '/approve');
        } catch (IntegrationApiException $parentException) {
            $e = $parentException->getPrevious();
            if ($e instanceof HttpExceptionInterface) {
                if ($msg = $this->getErrorMessage($e->getResponse())) {
                    foreach (self::REFUND_APPROVE_ERROR_MESSAGES as $key => $value) {
                        if (str_contains($msg, $key)) {
                            throw new IntegrationApiException($value);
                        }
                    }

                    throw new IntegrationApiException($msg);
                }

                // A not found error indicates that the refund is not eligible for a approval.
                if (404 == $e->getResponse()->getStatusCode()) {
                    throw new IntegrationApiException('We were unable to approve refund, try again later.');
                }
            }

            throw new IntegrationApiException('An unknown error occurred when processing your refund approval.');
        }
    }
}
