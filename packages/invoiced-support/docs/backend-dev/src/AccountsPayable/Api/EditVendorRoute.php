<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\Vendor;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * @extends AbstractEditModelApiRoute<Vendor>
 */
class EditVendorRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'name' => new RequestParameter(),
                'number' => new RequestParameter(),
                'active' => new RequestParameter(),
                'approval_workflow' => new RequestParameter(),
                'network_connection' => new RequestParameter(),
                'email' => new RequestParameter(),
                'address1' => new RequestParameter(),
                'address2' => new RequestParameter(),
                'city' => new RequestParameter(),
                'state' => new RequestParameter(),
                'country' => new RequestParameter(),
                'postal_code' => new RequestParameter(),
            ],
            requiredPermissions: ['vendors.edit'],
            modelClass: Vendor::class,
            features: ['accounts_payable'],
        );
    }
}
