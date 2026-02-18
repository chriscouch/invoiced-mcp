<?php

namespace App\Network\Api;

use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Network\Models\NetworkInvitation;

/**
 * @extends AbstractDeleteModelApiRoute<NetworkInvitation>
 */
class DeleteNetworkInvitationApiRoute extends AbstractDeleteModelApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['settings.edit'],
            modelClass: NetworkInvitation::class,
            features: ['network_invitations'],
        );
    }

    public function retrieveModel(ApiCallContext $context): NetworkInvitation
    {
        $invitation = parent::retrieveModel($context);

        $tenantId = $this->tenant->get()->id;
        if ($invitation->from_company_id != $tenantId) {
            throw $this->modelNotFoundError();
        }

        return $invitation;
    }
}
