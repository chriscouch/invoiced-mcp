<?php

namespace App\Integrations\Intacct\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Intacct\Models\IntacctAccount;
use App\Integrations\Intacct\Models\IntacctSyncProfile;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Intacct;

class ConnectIntacctRoute extends AbstractModelApiRoute
{
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
            modelClass: IntacctAccount::class,
            features: ['intacct'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Intacct $integration */
        $integration = $this->integrations->get(IntegrationType::Intacct, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            $account = new IntacctAccount();
        }

        foreach ($context->requestParameters as $k => $v) {
            $account->$k = $v;
        }

        if ($account->save()) {
            // Create a sync profile
            $company = $this->tenant->get();
            $syncProfile = IntacctSyncProfile::find($company->id());
            if (!$syncProfile) {
                $syncProfile = new IntacctSyncProfile();
                $syncProfile->save();
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
