<?php

namespace App\Core\Files\Api;

use App\Core\Files\Models\Attachment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * API endpoint to create attachments.
 */
class CreateAttachmentRoute extends AbstractCreateModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'parent_type' => new RequestParameter(
                    required: true,
                    allowedValues: ['credit_note', 'estimate', 'invoice', 'comment', 'payment', 'email', 'customer'],
                ),
                'parent_id' => new RequestParameter(
                    required: true,
                ),
                'file_id' => new RequestParameter(
                    required: true,
                ),
                'location' => new RequestParameter(
                    allowedValues: [Attachment::LOCATION_ATTACHMENT, Attachment::LOCATION_PDF],
                ),
            ],
            requiredPermissions: [],
            modelClass: Attachment::class
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        // Build query to lookup existing attachment.
        $query = [];
        foreach ($context->requestParameters as $key => $value) {
            $query[$key] = $value;
        }

        // Check if attachment already exists.
        if (Attachment::where($query)->oneOrNull()) {
            throw new InvalidRequest('Attachment already exists.');
        }

        return parent::buildResponse($context);
    }
}
