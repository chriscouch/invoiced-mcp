<?php

namespace App\Network\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Network\Command\DeleteNetworkConnection;
use App\Network\Models\NetworkConnection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractRetrieveModelApiRoute<Response>
 */
class DeleteNetworkConnectionApiRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private TenantContext $tenant,
        private DeleteNetworkConnection $deleteNetworkConnection
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: NetworkConnection::class,
            features: ['network'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        /** @var NetworkConnection $connection */
        $connection = parent::buildResponse($context);

        $tenantId = $this->tenant->get()->id;
        if ($connection->customer_id != $tenantId && $connection->vendor_id != $tenantId) {
            throw $this->modelNotFoundError();
        }

        $this->deleteNetworkConnection->remove($connection);

        return new Response('', 204);
    }
}
