<?php

namespace App\Integrations\ChartMogul\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\ChartMogulSyncJob;
use App\Integrations\ChartMogul\Models\ChartMogulAccount;

class ConnectChartMogulRoute extends AbstractModelApiRoute
{
    public function __construct(
        private Queue $queue,
        private TenantContext $tenant
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: ChartMogulAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $company = $this->tenant->get();
        $account = ChartMogulAccount::find($company->id());
        if (!$account) {
            $account = new ChartMogulAccount();
        }

        foreach ($context->requestParameters as $k => $v) {
            $account->$k = $v;
        }

        if ($account->save()) {
            // queue the first sync
            if ($account->enabled) {
                $this->queue->enqueue(ChartMogulSyncJob::class, ['accountId' => $account->id()], QueueServiceLevel::Batch);
            }

            return $account;
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a server error
        throw new ApiError('There was an error creating the '.$this->getModelName().'.');
    }
}
