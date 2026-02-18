<?php

namespace App\Integrations\QuickBooksDesktop\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\QuickBooksDesktop\QuickBooksDesktopSyncManager;

class ConnectQuickBooksDesktopRoute extends AbstractModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private QuickBooksDesktopSyncManager $syncManager,
    ) {
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        try {
            return $this->syncManager->enableQuickBooksEnterprise($this->tenant->get());
        } catch (SyncException $e) {
            throw new ApiError($e->getMessage());
        }
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            features: ['accounting_sync'],
        );
    }
}
