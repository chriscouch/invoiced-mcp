<?php

namespace App\Sending\Email\Api;

use App\Core\Authentication\Libs\UserContext;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Sending\Email\Models\EmailThread;
use App\Sending\Email\Models\EmailThreadNote;

class CreateInboxThreadNoteRoute extends AbstractCreateModelApiRoute
{
    public function __construct(private UserContext $userContext)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'note' => new RequestParameter(required: true),
            ],
            requiredPermissions: ['notes.create'],
            modelClass: EmailThreadNote::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $requestParameters = $context->requestParameters;
        $user = $this->userContext->getOrFail();
        $requestParameters['user_id'] = $user->id();
        $threadId = (int) $context->request->attributes->get('thread_id');
        $thread = EmailThread::findOrFail($threadId);
        $requestParameters['thread'] = $thread;
        $context = $context->withRequestParameters($requestParameters);

        return parent::buildResponse($context);
    }
}
