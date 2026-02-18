<?php

namespace App\Integrations\EarthClassMail\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\EarthClassMailSyncJob;
use App\Integrations\EarthClassMail\EarthClassMailClient;
use App\Integrations\EarthClassMail\Models\EarthClassMailAccount;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\EarthClassMail;

class ConnectEarthClassMailRoute extends AbstractModelApiRoute
{
    public function __construct(
        private IntegrationFactory $integrations,
        private EarthClassMailClient $client,
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
        $apiKey = $context->request->request->get('api_key');
        $inboxId = $context->request->request->getInt('inbox_id');

        /** @var EarthClassMail $integration */
        $integration = $this->integrations->get(IntegrationType::EarthClassMail, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            $account = new EarthClassMailAccount();
            $account->api_key = (string) $apiKey;
            try {
                $inboxes = $this->client->getInboxes($account);
                $account->inbox_id = $inboxes[0]['id'];
            } catch (IntegrationApiException $e) {
                throw new InvalidRequest($e->getMessage());
            }
        }

        if ($inboxId) {
            $account->inbox_id = $inboxId;
        }

        if ($account->save()) {
            if ($account->inbox_id) {
                $this->startSync($account);
            }

            return $account;
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a generic one
        throw new ApiError('There was an error updating the '.$this->getModelName().'.');
    }

    private function startSync(EarthClassMailAccount $account): void
    {
        $this->queue->enqueue(EarthClassMailSyncJob::class, [
            'tenant_id' => $account->tenant_id,
        ], QueueServiceLevel::Batch);
    }
}
