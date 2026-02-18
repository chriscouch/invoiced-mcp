<?php

namespace App\Integrations\AccountingSync\Api;

use App\AccountsReceivable\Models\Customer;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\AccountingCustomerMapping;
use App\Integrations\Enums\IntegrationType;

class CustomerAccountingSyncStatusRoute extends AbstractAccountingSyncStatusRoute
{
    const MAPPING_CLASS = AccountingCustomerMapping::class;
    const MAPPING_ID = 'customer_id';

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Customer::class,
        );
    }

    protected function getLegacyMetadataKeys(): array
    {
        return [
            'intacct_customer_id' => IntegrationType::Intacct->value,
            'netsuite_customer_id' => IntegrationType::NetSuite->value,
            'netsuite_id' => IntegrationType::NetSuite->value,
            'quickbooks_customer_id' => IntegrationType::QuickBooksOnline->value,
            'xero_customer_id' => IntegrationType::Xero->value,
        ];
    }
}
