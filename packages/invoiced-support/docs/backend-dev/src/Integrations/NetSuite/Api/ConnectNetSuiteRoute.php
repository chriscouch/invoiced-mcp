<?php

namespace App\Integrations\NetSuite\Api;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Integrations\AccountingSync\AccountingSyncModelFactory;
use App\Integrations\AccountingSync\Models\AccountingSyncProfile;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Exceptions\IntegrationApiException;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\NetSuite\Libs\NetSuiteApi;
use App\Integrations\NetSuite\Models\NetSuiteAccount;
use App\Integrations\Services\Netsuite;

class ConnectNetSuiteRoute extends AbstractModelApiRoute
{
    public function __construct(
        private IntegrationFactory $integrations,
        private NetSuiteApi $netSuiteApi,
        private TenantContext $tenant
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
            modelClass: NetSuiteAccount::class,
            features: ['netsuite'],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Netsuite $integration */
        $integration = $this->integrations->get(IntegrationType::NetSuite, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            $account = new NetSuiteAccount();
            $account->tenant_id = (int) $this->tenant->get()->id();
        }

        foreach ($context->requestParameters as $k => $v) {
            $account->$k = $v;
        }

        $this->setRestletDomain($account);

        if ($account->save()) {
            // Create a sync profile
            $syncProfile = AccountingSyncModelFactory::getSyncProfile(IntegrationType::NetSuite, $this->tenant->get());
            if (!$syncProfile) {
                $syncProfile = new AccountingSyncProfile();
                $syncProfile->integration = IntegrationType::NetSuite;
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

    /**
     * Sets the restlet_domain property on the NetSuiteAccount model by
     * looking it up. This also validates the.
     *
     * @throws InvalidRequest
     */
    private function setRestletDomain(NetSuiteAccount $account): void
    {
        try {
            $urls = $this->netSuiteApi->getDataCenterUrls($account);
        } catch (IntegrationApiException $e) {
            throw new InvalidRequest($e->getMessage());
        }

        $account->restlet_domain = $urls->dataCenterUrls->restDomain;
    }
}
