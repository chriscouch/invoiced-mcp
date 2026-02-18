<?php

namespace App\Sending\Email\Api;

use App\Core\RestApi\Routes\AbstractModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Utils\Enums\ObjectType;
use App\Sending\Email\Models\EmailThread;
use Symfony\Component\HttpFoundation\JsonResponse;

class RetrieveInboxThreadByDocumentRoute extends AbstractModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: null,
            requiredPermissions: [],
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $document_type = $context->request->attributes->get('document_type');
        $document_id = (int) $context->request->attributes->get('document_id');

        $model = EmailThread::where('related_to_type', ObjectType::fromTypeName($document_type)->value)
            ->where('related_to_id', $document_id)
            ->oneOrNull();

        // return empty response in case no model found
        if (!$model) {
            return new JsonResponse();
        }

        return $model;
    }
}
