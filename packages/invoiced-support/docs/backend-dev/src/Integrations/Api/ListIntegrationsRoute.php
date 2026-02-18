<?php

namespace App\Integrations\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Interfaces\IntegrationInterface;
use App\Integrations\Libs\IntegrationFactory;

class ListIntegrationsRoute extends AbstractApiRoute
{
    public function __construct(
        private IntegrationFactory $integrations,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $response = [];
        foreach ($this->integrations->all($this->tenant->get()) as $id => $integration) {
            $response[$id] = $this->expandIntegration($integration);
        }

        return $response;
    }

    /**
     * Expands an integration for the API.
     */
    private function expandIntegration(IntegrationInterface $integration): array
    {
        $connected = $integration->isConnected();

        return [
            'connected' => $connected,
            'name' => $connected ? $integration->getConnectedName() : null,
            'extra' => $connected ? $integration->getExtra() : new \stdClass(),
        ];
    }
}
