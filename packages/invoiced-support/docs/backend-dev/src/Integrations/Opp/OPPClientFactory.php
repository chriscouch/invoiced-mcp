<?php

namespace App\Integrations\Opp;

use App\PaymentProcessing\Libs\GatewayLogger;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OPPClientFactory
{

    public function __construct(
        private readonly string $oppClientUrl,
        private readonly string $oppUiUrl,
        private readonly string $oppUrl,
        private readonly string $oppAccessToken,
        private readonly string $oppKey,
        private readonly HttpClientInterface $httpClient,
        private readonly GatewayLogger $gatewayLogger,
        private readonly LoggerInterface $logger
    ) {
    }

    public function createOPPClient(string $oppAuthAccessToken, string $oppAuthKey): OPPClient
    {
        $client = new OPPClient(
            $this->oppClientUrl,
            $this->oppUiUrl,
            $this->oppUrl,
            $this->oppAccessToken,
            $this->oppKey,
            $this->httpClient,
            $this->gatewayLogger,
            $oppAuthAccessToken,
            $oppAuthKey
        );
        $client->setLogger($this->logger);

        return $client;
    }
}