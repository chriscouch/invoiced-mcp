<?php

namespace App\Integrations\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Interfaces\IntegrationInterface;
use App\Integrations\Libs\IntegrationFactory;

class RetrieveIntegrationRoute extends AbstractApiRoute
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

    public function buildResponse(ApiCallContext $context): mixed
    {
        $integration1 = $context->request->attributes->get('id');

        try {
            $type = IntegrationType::fromString($integration1);
            $integration = $this->integrations->get($type, $this->tenant->get());
        } catch (IntegrationException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return $this->expandIntegration($integration);
    }

    /**
     * Expands an integration for the API.
     */
    private function expandIntegration(IntegrationInterface $integration): array
    {
        return [
            'connected' => $integration->isConnected(),
            'name' => $integration->getConnectedName(),
            'extra' => $integration->getExtra(),
        ];
    }
}
