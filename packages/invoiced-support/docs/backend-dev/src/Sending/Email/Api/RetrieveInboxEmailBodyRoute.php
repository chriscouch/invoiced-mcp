<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Models\InboxEmail;
use EmailReplyParser\EmailReplyParser;

class RetrieveInboxEmailBodyRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(
        private EmailBodyStorageInterface $storage,
    ) {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: InboxEmail::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        /** @var InboxEmail $model */
        $model = parent::buildResponse($context);
        $emailText = $this->storage->retrieve($model, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
        $emailHTML = $this->storage->retrieve($model, EmailBodyStorageInterface::TYPE_HTML);

        return [
            'html' => $emailHTML,
            'text' => $emailText,
            'text_parsed' => EmailReplyParser::parseReply((string) $emailText),
        ];
    }
}
