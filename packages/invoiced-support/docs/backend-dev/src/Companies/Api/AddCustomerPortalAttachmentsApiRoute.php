<?php

namespace App\Companies\Api;

use App\Core\Files\Models\CustomerPortalAttachment;
use App\Core\Files\Models\File;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * Lists the attachments associated with the company.
 */
class AddCustomerPortalAttachmentsApiRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'file_id' => new RequestParameter(
                    required: true,
                    types: ['integer'],
                ),
            ],
            requiredPermissions: ['settings.edit'],
            modelClass: CustomerPortalAttachment::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $fileId = $context->requestParameters['file_id'];
        $attachment = new CustomerPortalAttachment();
        $attachment->file = File::findOrFail($fileId);
        $attachment->saveOrFail();

        return $attachment;
    }
}
