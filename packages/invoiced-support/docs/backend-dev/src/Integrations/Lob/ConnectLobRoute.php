<?php

namespace App\Integrations\Lob;

use App\Core\RestApi\Exception\ApiError;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Multitenant\TenantContext;
use App\Integrations\Enums\IntegrationType;
use App\Integrations\Libs\IntegrationFactory;
use App\Integrations\Services\Lob;

class ConnectLobRoute extends AbstractModelApiRoute
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
            requestParameters: [
                'return_envelopes' => new RequestParameter(),
                'custom_envelope' => new RequestParameter(),
                'use_color' => new RequestParameter(),
                'key' => new RequestParameter(),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: LobAccount::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var Lob $integration */
        $integration = $this->integrations->get(IntegrationType::Lob, $this->tenant->get());
        $account = $integration->getAccount();
        if (!$account) {
            $account = new LobAccount();
        }

        foreach ($context->requestParameters as $k => $v) {
            $account->$k = $v;
        }

        if ($account->save()) {
            return $account;
        }

        // get the first error
        if ($error = $this->getFirstError()) {
            throw $this->modelValidationError($error);
        }

        // no specific errors available, throw a server error
        throw new ApiError('There was an error creating the Invoice Delivery.');
    }
}
