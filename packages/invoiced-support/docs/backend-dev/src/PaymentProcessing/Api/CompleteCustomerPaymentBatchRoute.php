<?php

namespace App\PaymentProcessing\Api;

use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\Routes\AbstractRetrieveModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\Queue\Queue;
use App\Core\Queue\QueueServiceLevel;
use App\EntryPoint\QueueJob\CompleteCustomerPaymentBatchJob;
use App\PaymentProcessing\Enums\CustomerBatchPaymentStatus;
use App\PaymentProcessing\Models\CustomerPaymentBatch;

/**
 * @extends AbstractRetrieveModelApiRoute<CustomerPaymentBatch>
 */
class CompleteCustomerPaymentBatchRoute extends AbstractRetrieveModelApiRoute
{
    public function __construct(protected Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [],
            requiredPermissions: ['charges.create'],
            modelClass: CustomerPaymentBatch::class,
            features: ['accounts_receivable'],
        );
    }

    public function buildResponse(ApiCallContext $context): CustomerPaymentBatch
    {
        $batchPayment = parent::buildResponse($context);

        // Check if the batch payment has already been paid
        if (CustomerBatchPaymentStatus::Finished == $batchPayment->status) {
            throw new InvalidRequest('This payment batch has already completed.');
        }

        $this->queue->enqueue(CompleteCustomerPaymentBatchJob::class, [
            'id' => $batchPayment->id,
            'tenant_id' => $batchPayment->tenant_id,
        ], QueueServiceLevel::Batch);

        $batchPayment->status = CustomerBatchPaymentStatus::Processing;
        $batchPayment->saveOrFail();

        return $batchPayment;
    }
}
