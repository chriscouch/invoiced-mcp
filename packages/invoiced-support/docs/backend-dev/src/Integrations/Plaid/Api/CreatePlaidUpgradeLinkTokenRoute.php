<?php

namespace App\Integrations\Plaid\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Plaid\Libs\PlaidApi;
use App\Integrations\Plaid\Models\PlaidItem;

/**
 * @extends AbstractRetrieveModelApiRoute<array>
 */
class CreatePlaidUpgradeLinkTokenRoute extends AbstractRetrieveModelApiRoute
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
            modelClass: PlaidItem::class,
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var PlaidItem $plaidItem */
        $plaidItem = parent::buildResponse($context);
        $upgradeParameters = $context->requestParameters;
        $upgradeParameters['access_token'] = $plaidItem->access_token;

        try {
            return [
                'link_token' => $this->plaidApi->createLinkToken($this->tenant->get(), $upgradeParameters),
            ];
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }
    }
}
