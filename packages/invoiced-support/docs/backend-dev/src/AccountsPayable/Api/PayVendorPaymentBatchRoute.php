<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Enums\VendorBatchPaymentStatus;
use App\AccountsPayable\Models\VendorPaymentBatch;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\PayVendorPaymentBatchJob;

/**
 * @extends AbstractRetrieveModelApiRoute<VendorPaymentBatch>
 */
class PayVendorPaymentBatchRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(protected Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['vendor_payments.create'],
            modelClass: VendorPaymentBatch::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): VendorPaymentBatch
    {
        $batchPayment = parent::buildResponse($context);

        // Check if the batch payment has already been paid
        if (VendorBatchPaymentStatus::Finished == $batchPayment->status) {
            throw new InvalidRequest('This payment batch has already completed.');
        }

        $this->queue->enqueue(PayVendorPaymentBatchJob::class, [
            'id' => $batchPayment->id,
            'tenant_id' => $batchPayment->tenant_id,
        ], QueueServiceLevel::Batch);

        $batchPayment->status = VendorBatchPaymentStatus::Processing;
        $batchPayment->saveOrFail();

        return $batchPayment;
    }
}
