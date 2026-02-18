<?php

namespace App\Integrations\AccountingSync\Api;

use App\CashApplication\Models\Transaction;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\AccountingTransactionMapping;
use App\Integrations\Enums\IntegrationType;

class TransactionAccountingSyncStatusRoute extends AbstractAccountingSyncStatusRoute
{
    const MAPPING_CLASS = AccountingTransactionMapping::class;
    const MAPPING_ID = 'transaction_id';

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Transaction::class,
        );
    }

    protected function getLegacyMetadataKeys(): array
    {
        return [
            'intacct_payment_id' => IntegrationType::Intacct->value,
            'netsuite_payment_id' => IntegrationType::NetSuite->value,
            'netsuite_id' => IntegrationType::NetSuite->value,
            'quickbooks_payment_id' => IntegrationType::QuickBooksOnline->value,
            'xero_payment_id' => IntegrationType::Xero->value,
        ];
    }
}
