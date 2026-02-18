<?php

namespace App\Integrations\AccountingSync\Api;

use App\AccountsReceivable\Models\Invoice;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Integrations\AccountingSync\Models\AccountingInvoiceMapping;
use App\Integrations\Enums\IntegrationType;

class InvoiceAccountingSyncStatusRoute extends AbstractAccountingSyncStatusRoute
{
    const MAPPING_CLASS = AccountingInvoiceMapping::class;
    const MAPPING_ID = 'invoice_id';

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [],
            requiredPermissions: [],
            modelClass: Invoice::class,
        );
    }

    protected function getLegacyMetadataKeys(): array
    {
        return [
            'intacct_invoice_id' => IntegrationType::Intacct->value,
            'netsuite_invoice_id' => IntegrationType::NetSuite->value,
            'netsuite_id' => IntegrationType::NetSuite->value,
            'quickbooks_invoice_id' => IntegrationType::QuickBooksOnline->value,
            'xero_invoice_id' => IntegrationType::Xero->value,
        ];
    }
}
