<?php

namespace App\Integrations\AccountingSync\Api;

use App\CashApplication\Models\Payment;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\AccountingPaymentMapping;

class PaymentAccountingSyncStatusRoute extends AbstractAccountingSyncStatusRoute
{
    const MAPPING_CLASS = AccountingPaymentMapping::class;
    const MAPPING_ID = 'payment_id';

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Payment::class,
        );
    }
}
