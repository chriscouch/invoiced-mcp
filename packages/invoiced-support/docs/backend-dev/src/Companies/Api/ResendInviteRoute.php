<?php

namespace App\Companies\Api;

use App\Companies\Models\Member;
use App\Core\Authentication\Libs\UserContext;
use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resends an invitation to a user.
 *
 * @extends AbstractModelApiRoute<Member>
 */
class ResendInviteRoute extends AbstractModelApiRoute
{
    private const DEBOUNCE_PERIOD = '-5 minutes';

    public function __construct(private UserContext $userContext)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: ['business.admin'],
            modelClass: Member::class,
        );
    }

    public function buildResponse(ApiCallContext $context): Response
    {
        $this->setModelId($context->request->attributes->get('model_id'));
        $member = $this->retrieveModel($context);

        // debounce sending invites by using the updated_at timestamp
        $debounce = strtotime(self::DEBOUNCE_PERIOD);
        if ($member->updated_at > $debounce || $member->created_at > $debounce) {
            return new Response('', 204);
        }

        $member->sendInvite($this->userContext->getOrFail());

        return new Response('', 204);
    }
}
