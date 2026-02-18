<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Multitenant\TenantContext;
use Symfony\Component\HttpFoundation\Response;

class InboxMigrateRoute extends AbstractApiRoute
{
    public function __construct(private TenantContext $tenant)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['settings.edit'],
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $company = $this->tenant->get();

        $company->features->enable('inboxes');
        $company->features->disable('legacy_emails');

        return new Response('', 204);
    }
}
