<?php

namespace App\Network\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use App\Network\Models\NetworkConnection;
use App\Network\Traits\NetworkConnectionApiTrait;

/**
 * @extends AbstractRetrieveModelApiRoute<array>
 */
class RetrieveNetworkCustomerApiRoute extends AbstractRetrieveModelApiRoute
{
    use NetworkConnectionApiTrait;

    public function __construct(
        private TenantContext $tenant,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: NetworkConnection::class,
            features: ['network'],
        );
    }

    public function buildResponse(ApiCallContext $context): array
    {
        /** @var NetworkConnection $connection */
        $connection = parent::buildResponse($context);

        if ($connection->vendor_id != $this->tenant->get()->id) {
            throw $this->modelNotFoundError();
        }

        return $this->buildConnectionArray($connection, $connection->customer);
    }
}
