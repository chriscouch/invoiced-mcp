<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Sending\Email\Models\EmailThread;

class EditInboxThreadRoute extends AbstractEditModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'status' => new RequestParameter(
                    allowedValues: [EmailThread::STATUS_OPEN, EmailThread::STATUS_PENDING, EmailThread::STATUS_CLOSED],
                ),
                'assignee_id' => new RequestParameter(),
                'customer_id' => new RequestParameter(),
                'name' => new RequestParameter(),
            ],
            requiredPermissions: ['emails.send'],
            modelClass: EmailThread::class,
        );
    }
}
