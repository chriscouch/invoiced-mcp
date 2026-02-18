<?php

namespace App\Core\RestApi\ValueObjects;

use App\Core\Orm\Model;

final class ApiRouteDefinition
{
    /**
     * @param QueryParameter[]|null    $queryParameters      describes the available query parameters to the
     *                                                       caller in order for the API permission check to pass.
     *                                                       When this is true, then any requester that is not
     *                                                       a member will automatically be rejected with a
     *                                                       permission error.
     * @param RequestParameter[]|null  $requestParameters    describes the available request body parameters to the
     *                                                       in order to execute this API route. An empty list
     *                                                       implies that no specific user permissions are required.
     * @param array                    $requiredPermissions  Gets the list of permissions required by the user
     *                                                       API route and validation of each parameter
     * @param bool                     $requiresMember       Indicates whether a member is required to be the
     *                                                       API route and validation of each parameter
     * @param class-string<Model>|null $modelClass           the model class that is the subject of the API route
     * @param string[]                 $filterableProperties this indicates which properties can be filtered in a list models API route
     * @param bool                     $warn                 when true this will only log when validation fails and
     *                                                       not return an error message in the response
     */
    public function __construct(
        public readonly ?array $queryParameters,
        public readonly ?array $requestParameters,
        public readonly array $requiredPermissions,
        public readonly bool $requiresMember = false,
        public readonly ?string $modelClass = null,
        public readonly array $filterableProperties = [],
        public readonly array $features = [],
        public readonly bool $warn = false,
    ) {
    }
}
