<?php

namespace App\Integrations\QuickBooksDesktop\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\Exceptions\SyncException;
use App\Integrations\QuickBooksDesktop\QuickBooksDesktopSyncManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * This marks a specific record to be skipped during
 * syncs, i.e. an invoice.
 */
class SkipRecordRoute extends AbstractApiRoute
{
    private string $type;
    private string $object;
    private string $id;

    public function __construct(private TenantContext $tenant, private QuickBooksDesktopSyncManager $syncManager)
    {
    }

    /**
     * Gets the sync type.
     */
    public function getSyncType(): string
    {
        return $this->type;
    }

    /**
     * Gets the object type.
     */
    public function getObject(): string
    {
        return $this->object;
    }

    /**
     * Gets the object ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $this->type = (string) $context->request->request->get('type');
        $this->object = (string) $context->request->request->get('object');
        $this->id = (string) $context->request->request->get('id');

        try {
            $this->syncManager->skipRecord($this->tenant->get(), $this->type, $this->object, $this->id);
        } catch (SyncException $e) {
            throw new ApiError($e->getMessage());
        }

        return new Response('', 204);
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
