<?php

namespace App\Core\Files\Api;

use App\Core\Files\Models\Attachment;
use App\Core\RestApi\Routes\AbstractDeleteModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;

/**
 * Deletes the attachments associated with an object.
 */
class DeleteAttachmentsRoute extends AbstractDeleteModelApiRoute
{
    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Attachment::class,
        );
    }

    public function retrieveModel(ApiCallContext $context): Attachment
    {
        $parentType = $context->request->attributes->get('parent_type');
        $parentId = (int) $context->request->attributes->get('parent_id');
        $fileId = (int) $context->request->attributes->get('file_id');

        $this->model = Attachment::find([$parentType, $parentId, $fileId]);
        if (!$this->model) {
            throw $this->modelNotFoundError();
        }

        return $this->model;
    }
}
