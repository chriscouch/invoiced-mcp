<?php

namespace App\Integrations\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationException;
use App\Integrations\Libs\IntegrationFactory;
use Symfony\Component\HttpFoundation\Response;

class DisconnectIntegrationRoute extends AbstractApiRoute
{
    private string $integration;

    public function __construct(
        private IntegrationFactory $integrations,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
        );
    }

    /**
     * Gets the integration ID for this API route.
     */
    public function getIntegration(): string
    {
        return $this->integration;
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $this->integration = $context->request->attributes->get('id');
        $type = IntegrationType::fromString($this->integration);

        try {
            $integration = $this->integrations->get($type, $this->tenant->get());
        } catch (IntegrationException) {
            throw new InvalidRequest('No such integration: '.$this->integration);
        }

        try {
            $integration->disconnect();
        } catch (IntegrationException $e) {
            throw new ApiError($e->getMessage());
        }

        return new Response('', 204);
    }
}
