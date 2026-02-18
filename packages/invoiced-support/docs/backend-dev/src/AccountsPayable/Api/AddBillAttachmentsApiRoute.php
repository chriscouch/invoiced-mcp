<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\Bill;
use App\Core\Files\Models\BillAttachment;
use App\Core\Files\Models\File;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * Lists the attachments associated with the bill.
 */
class AddBillAttachmentsApiRoute extends AbstractCreateModelApiRoute
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
            requiredPermissions: ['bills.edit'],
            modelClass: BillAttachment::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $fileId = $context->requestParameters['file_id'];
        $id = (int) $context->request->attributes->get('parent_id');
        // Check if attachment already exists.
        if (BillAttachment::where('file_id', $fileId)
            ->where('bill_id', $id)
            ->oneOrNull()) {
            throw new InvalidRequest('Attachment already exists.');
        }

        $attachment = new BillAttachment();
        $attachment->file = File::findOrFail($fileId);
        $attachment->bill = Bill::findOrFail($id);
        $attachment->saveOrFail();

        return $attachment;
    }
}
