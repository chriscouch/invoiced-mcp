<?php

namespace App\Integrations\AuthorizeNet;

use App\PaymentProcessing\Libs\GatewayLogger;
use net\authorize\util\HttpClient;

/**
 * Decorates the Authorize.Net SDK HTTP client
 * in order to add our own response logging. It
 * is not possible to JSON encode the response
 * object because the SDK will manipulate it to
 * result in a different JSON string than what
 * was sent by the gateway.
 */
class AuthorizeNetHttpClient extends HttpClient
{
    public function __construct(private GatewayLogger $gatewayLogger)
    {
        parent::__construct();
    }

    public function _sendRequest(mixed $xmlRequest): bool|string
    {
        $response = parent::_sendRequest($xmlRequest);
        $this->gatewayLogger->logStringResponse((string) $response);

        return $response;
    }
}
