<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\PaymentProcessing\Exceptions\TestGatewayCredentialsException;
use App\PaymentProcessing\Operations\TestGatewayCredentials;
use Symfony\Component\HttpFoundation\Response;

/**
 * This class provides an API endpoint for testing user-supplied
 * payment gateway credentials.
 */
class TestGatewayCredentialsRoute extends AbstractApiRoute
{
    public function __construct(
        private TestGatewayCredentials $operation,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'gateway' => new RequestParameter(
                    required: true,
                    types: ['string'],
                ),
                'credentials' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ),
            ],
            requiredPermissions: [],
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $gatewayId = (string) $context->requestParameters['gateway'];
        $credentials = (array) $context->requestParameters['credentials'];

        try {
            $this->operation->testCredentials($gatewayId, $credentials);
        } catch (TestGatewayCredentialsException $e) {
            throw new InvalidRequest('Unable to verify payment gateway credentials: '.$e->getMessage());
        }

        return new Response('', 204);
    }
}
