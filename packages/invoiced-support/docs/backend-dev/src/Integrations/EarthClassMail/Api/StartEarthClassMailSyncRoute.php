<?php

namespace App\Integrations\EarthClassMail\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\EarthClassMailSyncJob;
use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\EarthClassMail;
use Symfony\Component\HttpFoundation\Response;

class StartEarthClassMailSyncRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private IntegrationFactory $integrations,
        private Queue $queue,
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: EarthClassMailAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var EarthClassMail $integration */
        $integration = $this->integrations->get(IntegrationType::EarthClassMail, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            throw new InvalidRequest('Earth Class Mail is not configured');
        }

        if (!$account->inbox_id) {
            throw new InvalidRequest('You must first select an inbox to start a sync.');
        }

        $this->startSync($account);

        return new Response('', 204);
    }

    private function startSync(EarthClassMailAccount $account): void
    {
        $this->queue->enqueue(EarthClassMailSyncJob::class, [
            'tenant_id' => $account->tenant_id,
        ], QueueServiceLevel::Batch);
    }
}
