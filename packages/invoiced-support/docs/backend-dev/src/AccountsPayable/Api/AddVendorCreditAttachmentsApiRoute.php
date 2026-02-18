<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorCredit;
use App\Core\Files\Models\File;
use App\Core\Files\Models\VendorCreditAttachment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * Lists the attachments associated with the vendor payment.
 */
class AddVendorCreditAttachmentsApiRoute extends AbstractCreateModelApiRoute
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
            modelClass: VendorCreditAttachment::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $fileId = $context->requestParameters['file_id'];
        $id = (int) $context->request->attributes->get('vendor_credit_id');
        // Check if attachment already exists.
        if (VendorCreditAttachment::where('file_id', $fileId)
            ->where('vendor_credit_id', $id)
            ->oneOrNull()) {
            throw new InvalidRequest('Attachment already exists.');
        }

        $attachment = new VendorCreditAttachment();
        $attachment->file = File::findOrFail($fileId);
        $attachment->vendor_credit = VendorCredit::findOrFail($id);
        $attachment->saveOrFail();

        return $attachment;
    }
}
