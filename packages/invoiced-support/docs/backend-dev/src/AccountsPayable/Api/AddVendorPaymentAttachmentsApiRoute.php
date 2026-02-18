<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPayment;
use App\Core\Files\Models\File;
use App\Core\Files\Models\VendorPaymentAttachment;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractCreateModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;

/**
 * Lists the attachments associated with the vendor payment.
 */
class AddVendorPaymentAttachmentsApiRoute extends AbstractCreateModelApiRoute
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
            requiredPermissions: ['vendor_payments.edit'],
            modelClass: VendorPaymentAttachment::class,
        );
    }

    public function buildResponse(ApiCallContext $context): mixed
    {
        $fileId = $context->requestParameters['file_id'];
        $vendorPaymentId = (int) $context->request->attributes->get('vendor_payment_id');
        // Check if attachment already exists.
        if (VendorPaymentAttachment::where('file_id', $fileId)
            ->where('vendor_payment_id', $vendorPaymentId)
            ->oneOrNull()) {
            throw new InvalidRequest('Attachment already exists.');
        }

        $attachment = new VendorPaymentAttachment();
        $attachment->file = File::findOrFail($fileId);
        $attachment->vendor_payment = VendorPayment::findOrFail($vendorPaymentId);
        $attachment->saveOrFail();

        return $attachment;
    }
}
