<?php

namespace App\Notifications\Api;

use App\Companies\Models\Member;
use App\Core\RestApi\Routes\AbstractApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Orm\ACLModelRequester;
use App\Notifications\Libs\MigrateV2Notifications;
use Symfony\Component\HttpFoundation\Response;

class ConvertUserNotificationsRoute extends AbstractApiRoute
{
    public function __construct(private MigrateV2Notifications $migrate)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['business.admin'],
            requiresMember: true,
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        /** @var Member $member */
        $member = ACLModelRequester::get();
        $this->migrate->migrate($member->tenant());

        return new Response('', 204);
    }
}
