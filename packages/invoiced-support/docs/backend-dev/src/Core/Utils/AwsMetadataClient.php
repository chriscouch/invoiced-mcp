<?php

namespace App\Core\Utils;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AwsMetadataClient
{
    const TASK_RUNNING = 'RUNNING';

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $ecsMetadataUri,
        private readonly string $ecsAgentUri
    ) {
    }

    public function isShutDownDesired(): bool
    {
        if (!$this->ecsMetadataUri) {
            return false;
        }

        $body = null;
        try {
            $response = $this->client->request('GET', $this->ecsMetadataUri.'/task');
            $body = $response->toArray();
        } catch (ExceptionInterface) {
            // do nothing on failure
        }

        return is_array($body) && isset($body['DesiredStatus']) && self::TASK_RUNNING !== $body['DesiredStatus'];
    }

    public function enableProtection(): void
    {
        $this->setProtectionState([
            'protectionEnabled' => true,
            'expiresInMinutes' => 2880,
        ]);
    }

    public function disableProtection(): void
    {
        $this->setProtectionState(['protectionEnabled' => false]);
    }

    private function setProtectionState(array $params): void
    {
        if (!$this->ecsAgentUri) {
            return;
        }

        try {
            $this->client->request('PUT', $this->ecsAgentUri.'/task-protection/v1/state', [
                'json' => $params,
            ]);
        } catch (ExceptionInterface) {
            // do nothing on failure
        }
    }
}
