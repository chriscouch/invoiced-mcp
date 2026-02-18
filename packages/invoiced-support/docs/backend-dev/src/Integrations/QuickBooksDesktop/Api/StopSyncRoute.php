<?php

namespace App\Integrations\QuickBooksDesktop\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\QuickBooksDesktop\QuickBooksDesktopSyncManager;

class StopSyncRoute extends AbstractApiRoute
{
    private string $id;

    public function __construct(private TenantContext $tenant, private QuickBooksDesktopSyncManager $syncManager)
    {
    }

    /**
     * Gets the job ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function buildResponse(ApiCallContext $context): array
    {
        $this->id = $context->request->attributes->get('id');

        try {
            $job = $this->syncManager->stopJob($this->tenant->get(), $this->id);
        } catch (SyncException $e) {
            throw new ApiError($e->getMessage());
        }

        return $job->toArray();
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
        );
    }
}
