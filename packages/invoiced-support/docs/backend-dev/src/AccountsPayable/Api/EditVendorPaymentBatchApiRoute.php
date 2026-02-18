<?php

namespace App\AccountsPayable\Api;

use App\AccountsPayable\Models\VendorPaymentBatch;
use App\Core\RestApi\Routes\AbstractEditModelApiRoute;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Core\Queue\Queue;

/**
 * @extends AbstractEditModelApiRoute<VendorPaymentBatch>
 */
class EditVendorPaymentBatchApiRoute extends AbstractEditModelApiRoute
{
    public function __construct(protected Queue $queue)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: [],
            requestParameters: [
                'bank_account' => new RequestParameter(
                    types: ['numeric'],
                ),
            ],
            requiredPermissions: ['vendor_payments.edit'],
            modelClass: VendorPaymentBatch::class,
            features: ['accounts_payable'],
        );
    }
}
