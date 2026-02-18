<?php

namespace App\Integrations\Plaid\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Plaid\Libs\PlaidApi;

class CreatePlaidLinkTokenRoute extends AbstractApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private PlaidApi $plaidApi,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        try {
            return [
                'link_token' => $this->plaidApi->createLinkToken($this->tenant->get(), $context->requestParameters),
            ];
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
