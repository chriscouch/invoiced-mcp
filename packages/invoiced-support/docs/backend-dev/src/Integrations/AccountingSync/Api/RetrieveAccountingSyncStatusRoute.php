<?php

namespace App\Integrations\AccountingSync\Api;

use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\AccountingSyncStatus;
use App\Integrations\Enums\IntegrationType;

/**
 * @extends AbstractModelApiRoute<AccountingSyncStatus>
 */
class RetrieveAccountingSyncStatusRoute extends AbstractModelApiRoute
{
    private IntegrationType $integration;

    public function setIntegration(IntegrationType $integration): self
    {
        $this->integration = $integration;

        return $this;
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: AccountingSyncStatus::class,
        );
    }

    public function buildResponse(ApiCallContext $context): AccountingSyncStatus
    {
        $syncStatus = AccountingSyncStatus::where('integration_id', $this->integration->value)->oneOrNull();
        if (!$syncStatus) {
            $syncStatus = new AccountingSyncStatus();
            $syncStatus->running = false;
        }

        return $syncStatus;
    }
}
